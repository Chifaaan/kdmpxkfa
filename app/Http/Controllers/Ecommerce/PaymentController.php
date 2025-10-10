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
    public function index(Order $order)
    {
        // Set Midtrans configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');

        $billingData = session('checkout.billing');

        $orderItems = $order->orderItems;
        $item_details = [];
        foreach ($orderItems as $item) {
            $item_details[] = [
                'id' => $item->product_id,
                'price' => (int) $item->unit_price,
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
                'last_name' => $billingData['last_name'] ?? $order->user->last_name,
                'email' => $billingData['email'] ?? $order->user->email,
                'phone' => $billingData['phone'] ?? $order->user->phone,
            ],
            'item_details' => $item_details
        ];

        try {
            $paymentUrl = Snap::createTransaction($params)->redirect_url;
            return redirect()->away($paymentUrl);
            // dd($paymentUrl);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function processPaymentGateway(Request $request, CartService $cartService, DigikopTransactionService $transactionService)
    {
        // Check if billing information exists in session
        if (!session('checkout.billing')) {
            return redirect()->route('checkout')->with('error', 'Please complete billing information first.');
        }

        // Get cart items from session (set during checkout process)
        $cartItems = $request->input('cart', []);

        Log::debug('Cart items for payment gateway process:', $cartItems);

        if (empty($cartItems)) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        $request->validate([
            'source_of_fund' => 'required|string|',
            'cart' => 'required|array|min:1',
        ]);

        try {
            // Calculate total amount from localStorage cart items
            $totalAmount = array_sum(array_map(function ($item) {
                return $item['price'] * $item['quantity'] * $item['content'];
            }, $cartItems));

            \DB::beginTransaction();

            $billingData = session('checkout.billing');
            $shippingData = session('checkout.shipping');

            // Create the order with payment_type as 'va' (Virtual Account) for payment gateway
            $order = Order::create([
                'transaction_number' => Order::generateTransactionNumber(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id,
                'source_of_fund' => $request->source_of_fund,
                'status' => OrderStatusEnum::CREATED->value,
                'account_no' => auth()->user()->userProfile->bank_account['nomor_rekening'] ?? '', // This would need to be set based on your business logic
                'account_bank' => auth()->user()->userProfile->bank_account['nomor_rekening'] ?? '', // This would need to be set based on your business logic
                'payment_type' => 'va', // Virtual Account for payment gateway
                'payment_method' => 'midtrans', // Specific to payment gateway
                'va_number' => '', // Will be filled by Midtrans later
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

            \DB::commit();

            // Redirect to the payment page to generate snap token
            return redirect()->route('ecommerce.paytest', $order->id)->with('success', 'Order placed successfully, redirecting to payment gateway!');
        } catch (ValidationException $e) {
            // Re-throw validation exceptions as they are already properly formatted
            \DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            \DB::rollBack();

            \Log::error('Order creation for payment gateway failed with exception: ' . $e->getMessage());

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

            \Log::error('Order creation for payment gateway failed with throwable: ' . $e->getMessage());

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

    // Alias method for generateSnapToken to maintain backward compatibility
    public function generateSnapToken(Order $order)
    {
        return $this->index($order);
    }
}
