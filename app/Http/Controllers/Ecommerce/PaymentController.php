<?php

namespace App\Http\Controllers\Ecommerce;

use App\Enums\OrderStatusEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Services\CartService;
use App\Services\DigikopTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentController extends Controller
{
    public function index(Request $request, CartService $cartService, DigikopTransactionService $transactionService)
    {
        // Check if billing information exists in session
        if (!session('checkout.billing')) {
            return redirect()->route('checkout')->with('error', 'Please complete billing information first.');
        }

        // Get cart items from session (set during checkout process)
        $cartItems = $request->input('cart', []);

        //        Log::debug('Cart items for payment process:', $cartItems);

        if (empty($cartItems)) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        // Add cart items to request so CartService can process them
        $request->merge(['cart_items' => $cartItems]);

        //        Log::info('Cart items for order placement:', $cartItems);

        // Temporarily override CartService's getCartItems method
        //        $originalGetCartItems = function() use ($cartItems) {
        //            return $cartItems;
        //        };

        // Use reflection to temporarily override the method
        //        $cartServiceReflection = new \ReflectionClass($cartService);
        //        $getCartItemsMethod = $cartServiceReflection->getMethod('getCartItems');

        // Since we can't directly override the method, we'll pass the cart items differently
        // Let's create a temporary solution by modifying how we call the service

        // Determine if it's a payment gateway request by checking the route name
        $isPaymentGateway = $request->route()->getName() === 'payment.gateway';

        // Validate the request differently based on whether it's a payment gateway request
        if ($isPaymentGateway) {
            $request->validate([
                'source_of_fund' => 'required|string|',
                'cart' => 'required|array|min:1',
            ]);
            // Set default payment type for payment gateway
            $paymentType = 'va';
            $paymentMethod = 'payment-gateway';
        } else {
            $request->validate([
                'source_of_fund' => 'required|string|',
                'payment_type' => 'required|string|',
                'cart' => 'required|array|min:1',
            ]);
            $paymentType = $request->payment_type;
            $paymentMethod = 'mandiri'; // Default for non-payment gateway
        }

        try {
            // Validate credit limit before processing payment
            $user = auth()->user();

            // Calculate total amount from localStorage cart items
            $totalAmount = array_sum(array_map(function ($item) {
                return $item['price'] * $item['quantity'] * $item['content'];
            }, $cartItems));

            // Validate credit limit using tenant_id
            // $creditValidation = $transactionService->validateCreditLimit($user->tenant_id, $totalAmount);

            // if (!$creditValidation['valid']) {
            //     // Handle credit limit exceeded
            //     throw ValidationException::withMessages([
            //         'credit_limit_error' => $creditValidation['message'],
            //     ]);
            // }

            \DB::beginTransaction();

            $billingData = session('checkout.billing');
            $shippingData = session('checkout.shipping');

            // Create the order
            $order = Order::create([
                'transaction_number' => Order::generateTransactionNumber(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id,
                'source_of_fund' => $request->source_of_fund,
                'status' => OrderStatusEnum::PENDING->value,
                'account_no' => auth()->user()->userProfile->bank_account['nomor_rekening'] ?? '', // This would need to be set based on your business logic
                'account_bank' => auth()->user()->userProfile->bank_account['nomor_rekening'] ?? '', // This would need to be set based on your business logic
                'payment_type' => $paymentType, // Use the determined payment type
                'payment_method' => $paymentMethod, // Use the appropriate payment method
                'va_number' => auth()->user()->apotek->bankAccount->account_number ?? '0000000000000', // No Rek KFA -> branch
                'subtotal' => $totalAmount,
                'tax_amount' => $totalAmount * 0.11, // You can calculate tax based on your business logic
                'shipping_amount' => 0, // You can calculate shipping based on your business logic
                'discount_amount' => 0,
                'total_price' => round($totalAmount * 1.11),
                'billing_name' => $billingData['first_name'] . ' ' . $billingData['last_name'],
                'billing_email' => $billingData['email'],
                'billing_phone' => $billingData['phone'],
                'billing_address' => $billingData['address'],
                'billing_city' => $billingData['city'],
                'billing_state' => $billingData['state'],
                'billing_zip' => $billingData['zip'],
                'shipping_name' => $shippingData['first_name'] . ' ' . $shippingData['last_name'],
                'shipping_address' => $shippingData['address'],
                'shipping_city' => $shippingData['city'],
                'shipping_state' => $shippingData['state'],
                'shipping_zip' => $shippingData['zip'],
                'customer_notes' => $billingData['notes'] ?? null,
            ]);

            // Create order items
            foreach ($cartItems as $cartItem) {
                $product = Product::find($cartItem['id']);

                if (!$product) {
                    continue;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'product_description' => $product->description,
                    'unit_price' => $cartItem['price'], // This is now the price per order unit
                    'total_price' => $cartItem['price'] * $cartItem['quantity'] * $product->content,
                    'quantity' => $cartItem['quantity'],
                    'base_quantity' => $cartItem['quantity'] * $product->content,
                    'order_unit' => $product->order_unit,
                    'base_uom' => $product->base_uom,
                    'content' => $product->content,
                ]);
            }

            // Notify pharmacy admins about the new order
            $orderingUser = $order->user; // Get the user who placed the order
            if ($orderingUser && $orderingUser->apotek_id) {
                // Find all users who are admins for this specific apotek (pharmacy admins)
                $pharmacyAdmins = User::where('apotek_id', $orderingUser->apotek_id)
                    ->whereHas('roles', function ($query) {
                        $query->where('name', RoleEnum::ADMIN_APOTEK->value); // Only admin-apotek role gets notifications
                    })
                    ->get();
                Log::info('Pharmacy admins: ', [$pharmacyAdmins]);
                // Send notification to each pharmacy admin
                foreach ($pharmacyAdmins as $admin) {
                    $admin->notify(new NewOrderNotification($order, $orderingUser));
                }
            }

            // For payment gateway, prepare Midtrans and redirect to payment page
            if ($isPaymentGateway) {
                // Set Midtrans configuration
                Config::$serverKey = config('midtrans.server_key');
                Config::$isProduction = config('midtrans.is_production');
                Config::$isSanitized = config('midtrans.is_sanitized');
                Config::$is3ds = config('midtrans.is_3ds');

                $orderItems = $order->orderItems;
                $item_details = [];
                foreach ($orderItems as $item) {
                    $item_details[] = [
                        'id' => $item->product_id,
                        'price' => round($item->unit_price * $item->content * 1.11),
                        'quantity' => $item->quantity,
                        'name' => $item->product->name,
                    ];
                }

                $params = [
                    'transaction_details' => [
                        'order_id' => $order->transaction_number,
                        'gross_amount' => $order->total_price,
                    ],
                    'customer_details' => [
                        'first_name' => $billingData['first_name'] ?? $order->user->first_name,
                        'email' => $billingData['email'] ?? $order->user->email,
                        'phone' => $billingData['phone'] ?? $order->user->phone,
                    ],
                    'item_details' => $item_details
                ];
                $snapToken = Snap::getSnapToken($params);

                // Save the snap token to the order
                $order->update(['snap_token' => $snapToken]);

                \DB::commit();

                return Inertia::render('ecommerce/paytest', [
                    'orderId' => $order->id,
                    'snapToken' => $snapToken,
                    'transaction_number' => $order->transaction_number,
                    'order' => $order
                ]);
            } else {
                // For non-payment gateway (credit co-op), complete the order immediately
                \DB::commit();

                // Clear cart and checkout data from session after successful payment
                // Log::debug("message", session('cart'));
                // // session()->forget(['cart', 'checkout.billing', 'checkout.shipping']);

                // Redirect to order confirmation page
                return redirect()->route('order.complete', $order->id)->with('success', 'Order placed successfully!');
            }
        } catch (ValidationException $e) {
            // Re-throw validation exceptions as they are already properly formatted
            \DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            \DB::rollBack();

            \Log::error('Order creation failed with exception: ' . $e->getMessage());

            // Check if this is a pharmacy mapping error
            if (
                strpos($e->getMessage(), 'mapped') !== false ||
                strpos($e->getMessage(), 'pharmacy') !== false ||
                strpos($e->getMessage(), 'apotek') !== false
            ) {
                throw ValidationException::withMessages([
                    'mapping_error' => 'Koperasi belum dimapping dengan Apotek KF, Silakan hubungi administrator.',
                ]);
            }

            // Generic error for other exceptions
            throw ValidationException::withMessages([
                'generic_payment_error' => 'A critical error occurred. Our team has been notified. Please try again later.',
            ]);
        } catch (\Throwable $e) {
            \DB::rollBack();

            \Log::error('Order creation failed with throwable: ' . $e->getMessage());

            // Check if this is specifically a credit limit issue
            if (strpos($e->getMessage(), 'credit') !== false) {
                throw ValidationException::withMessages([
                    'credit_limit_error' => 'Insufficient credit limit to complete this transaction.',
                ]);
            }

            // Check if this is a pharmacy mapping error
            if (
                strpos($e->getMessage(), 'mapped') !== false ||
                strpos($e->getMessage(), 'pharmacy') !== false ||
                strpos($e->getMessage(), 'apotek') !== false
            ) {
                throw ValidationException::withMessages([
                    'mapping_error' => 'Koperasi belum dimapping dengan Apotek KF, Silakan hubungi administrator.',
                ]);
            }

            // Generic error for other exceptions
            throw ValidationException::withMessages([
                'generic_payment_error' => 'A critical error occurred. Our team has been notified. Please try again later.',
            ]);
        }
    }

    public function handleMidtransCallback(Request $request)
    {
        $notification = $request->all();

        $order_id = $notification['order_id'];
        $transaction_status = $notification['transaction_status'];
        $fraud_status = $notification['fraud_status'] ?? 'accept';

        $order = Order::where('transaction_number', $order_id)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Update order status based on transaction status
        if ($transaction_status == 'capture') {
            if ($fraud_status == 'challenge') {
                $order->update(['status' => 'challenged']);
            } else if ($fraud_status == 'accept') {
                $order->update(['status' => 'paid']);
            }
        } else if ($transaction_status == 'settlement') {
            $order->update(['status' => 'paid']);
        } else if ($transaction_status == 'cancel') {
            $order->update(['status' => 'cancelled']);
        } else if ($transaction_status == 'expire') {
            $order->update(['status' => 'expired']);
        } else if ($transaction_status == 'pending') {
            $order->update(['status' => 'pending']);
        }

        return response()->json(['status' => 'OK']);
    }

    // Method to generate a new snap token for an existing order
    public function generateSnapToken(Order $order)
    {
        // Set Midtrans configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');

        $orderItems = $order->orderItems;
        $item_details = [];
        foreach ($orderItems as $item) {
            $item_details[] = [
                'id' => $item->product_id,
                'price' => round($item->unit_price * $item->content * 1.11),
                'quantity' => $item->quantity,
                'name' => $item->product->name,
            ];
        }

        $params = [
            'transaction_details' => [
                'order_id' => $order->transaction_number,
                'gross_amount' => $order->total_price,
            ],
            'customer_details' => [
                'first_name' => $order->user->first_name ?? $order->billing_name,
                'email' => $order->user->email ?? $order->billing_email,
                'phone' => $order->user->phone ?? $order->billing_phone,
            ],
            'item_details' => $item_details
        ];
        $snapToken = Snap::getSnapToken($params);

        // Save the snap token to the order
        $order->update(['snap_token' => $snapToken]);

        return response()->json([
            'snapToken' => $snapToken,
            'transaction_number' => $order->transaction_number,
        ]);
    }

    // Method to handle the payment test page for payment gateway orders
    public function processPaymentGateway(Order $order)
    {
        // You can implement any specific logic needed for payment test page here
        // For now, this can just return the same view as the index method would for payment gateway

        $order->load('orderItems.product'); // Load related data if needed

        // Set Midtrans configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');

        $billingData = session('checkout.billing') ?? [];

        $orderItems = $order->orderItems;
        $item_details = [];
        foreach ($orderItems as $item) {
            $item_details[] = [
                'id' => $item->product_id,
                'price' => round($item->unit_price * $item->content * 1.11),
                'quantity' => $item->quantity,
                'name' => $item->product->name,
            ];
        }

        $params = [
            'transaction_details' => [
                'order_id' => $order->transaction_number,
                'gross_amount' => $order->total_price,
            ],
            'customer_details' => [
                'first_name' => $billingData['first_name'] ?? $order->user->first_name,
                'email' => $billingData['email'] ?? $order->user->email,
                'phone' => $billingData['phone'] ?? $order->user->phone,
            ],
            'item_details' => $item_details
        ];
        $snapToken = Snap::getSnapToken($params);

        // Save the snap token to the order
        $order->update(['snap_token' => $snapToken]);

        return Inertia::render('ecommerce/paytest', [
            'orderId' => $order->id,
            'snapToken' => $snapToken,
            'transaction_number' => $order->transaction_number,
            'order' => $order
        ]);
    }
}
