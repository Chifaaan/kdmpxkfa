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

use function Pest\Laravel\json;

class CartController extends Controller
{
    // public function index(CartService $cartService)
    // {
    //     return Inertia::render(
    //         'ecommerce/cart',
    //         [
    //             'cartItems' => $cartService->getCartItems(),
    //         ]
    //     );
    // }

    // public function store(Request $request, CartService $cartService)
    // {
    //     // dump('Product: ', $request->json()->get('productId'));
    //     $request->mergeIfMissing((['quantity' => 1]));

    //     $data = $request->validate([
    //         'quantity' => ['nullable', 'integer', 'min:1'],
    //     ]);
    //     $cartService->addItemToCart(Product::find($request['productId']), $data['quantity']);

    //     return back()->with('success', 'Item added to cart successfully.');
    // }

    // public function update(Request $request, CartService $cartService)
    // {
    //     dump($request->all());
    //     $request->validate([
    //         'quantity' => ['integer', 'min:1'],
    //     ]);

    //     $quantity = $request->input('quantity');
    //     $cartService->updateItemQuantity($request['product'], $quantity);

    //     return back()->with('success', 'Item quantity updated successfully.');
    // }

    // public function destroy(Product $product, CartService $cartService)
    // {
    //     $cartService->removeItemFromCart($product->id);

    //     return back()->with('success', 'Item removed from cart successfully.');
    // }

    public function checkoutForm()
    {
        // Get cart items from localStorage (passed via request)
        // $cartData = $request->input('cart', '[]');
        // $cartItems = json_decode($cartData, true);

        // $cartItems = session('cart', '[]');

        // // If no cart items from localStorage, try to get from session as fallback
        // if (empty($cartItems)) {
        //     $cartItems = session('cart', []);
        // }

        // if (empty($cartItems)) {
        //     return redirect()->route('cart')->with('error', 'Your cart is empty.');
        // }

        // Log::info('Cart items for checkout:', $cartItems);

        // // Calculate cart totals
        // $totalQuantity = array_sum(array_column($cartItems, 'quantity'));
        // $subtotal = array_sum(array_map(function($item) {
        //     return $item['price'] * $item['quantity'];
        // }, $cartItems));

        // $tax = $subtotal*0.11;
        // $shipping_amount = 0;
        // $grandTotal = $tax + $subtotal + $shipping_amount;

        // Get existing checkout data from session if available
        $billingData = session('checkout.billing', []);
        $shippingData = session('checkout.shipping', []);

        // Determine if shipping is same as billing
        $sameAsBilling = empty($shippingData) ||
            ($billingData &&
                isset($billingData['first_name']) && isset($shippingData['first_name']) &&
                $billingData['first_name'] === $shippingData['first_name'] &&
                isset($billingData['last_name']) && isset($shippingData['last_name']) &&
                $billingData['last_name'] === $shippingData['last_name'] &&
                isset($billingData['email']) && isset($shippingData['email']) &&
                $billingData['email'] === $shippingData['email'] &&
                isset($billingData['phone']) && isset($shippingData['phone']) &&
                $billingData['phone'] === $shippingData['phone'] &&
                isset($billingData['address']) && isset($shippingData['address']) &&
                $billingData['address'] === $shippingData['address'] &&
                isset($billingData['city']) && isset($shippingData['city']) &&
                $billingData['city'] === $shippingData['city'] &&
                isset($billingData['state']) && isset($shippingData['state']) &&
                $billingData['state'] === $shippingData['state'] &&
                isset($billingData['zip']) && isset($shippingData['zip']) &&
                $billingData['zip'] === $shippingData['zip']);

        // session([
        //     'checkout.billing' => $billingData,
        //     'checkout.shipping' => $shippingData,
        //     'cart' => $cartItems // Store cart items in session for payment page
        // ]);

        return Inertia::render('ecommerce/checkout', [
            // 'cartItems' => $cartItems,
            // 'totalQuantity' => $totalQuantity,
            // 'totalPrice' => $grandTotal,
            // 'subtotal' => $subtotal,
            // 'shipping' => $shipping_amount,
            // 'tax' => $tax,
            'billingData' => $billingData,
            'shippingData' => $shippingData,
            'sameAsBilling' => $sameAsBilling,
        ]);
    }

    public function processCheckout(Request $request)
    {
        // Get cart items from localStorage (passed via request)
        // $cartData = session('cart', '[]');
        // dd('Cart items for process checkout all:', $request->all());
        // $cartItems = $cartData;

        // Log::info('Cart items for payment pre:', $cartItems);

        // If no cart items from localStorage, try to get from session as fallback
        //        if (empty($cartItems)) {
        //            $cartItems = session('cart', []);
        //        }

        // if (empty($cartItems)) {
        //     return redirect()->route('cart')->with('error', 'Your cart is empty.');
        // }

        // Validate and process billing/shipping data
        $billingData = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shippingData = $request->input('same_as_billing') ? $billingData : $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'string', 'max:20'],
        ]);

        //        Log::info('Billing Data:', $billingData);
        //        Log::info('Shipping Data:', $shippingData);

        // Store billing/shipping info in session for payment page
        session([
            'checkout.billing' => $billingData,
            'checkout.shipping' => $shippingData,
            // 'cart' => $cartItems // Store cart items in session for payment page
        ]);

        //        Log::info('Check Session Billing and Shipping', session('checkout.billing'));

        // Log::info('Cart saved in session:', session('cart'));
        // dd(session('cart'));

        // Redirect to payment page
        return redirect()->route('payment');
    }

    // Show payment form
    public function paymentForm(Request $request)
    {
        // Get cart items from session
        // $cartItems = session('cart', []);
        // Log::info('Cart items for payment post:', session()->all());

        // // Check if billing information exists in session
        // if (! session('checkout.billing')) {
        //     return redirect()->route('checkout')->with('error', 'Please complete billing information first.');
        // }

        // check cart empty
        // if (count($cartItems) == 0) {
        //     return redirect()->route('carts.index')->with('error', 'Your cart is empty.');
        // }

        // Calculate cart totals
        // $totalQuantity = array_sum(array_column($cartItems, 'quantity'));
        // $subtotal = array_sum(array_map(function($item) {
        //     return $item['price'] * $item['quantity'];
        // }, $cartItems));

        // $tax = $subtotal*0.11;
        // $shipping_amount = 0;
        // $grandTotal = $tax + $subtotal + $shipping_amount;

        return Inertia::render('ecommerce/payment', [
            // 'cartItems' => $cartItems,
            // 'totalPrice' => $grandTotal,
            // 'totalQuantity' => $totalQuantity,
            // 'subtotal' => $subtotal,
            // 'shipping_amount' => $shipping_amount,
            // 'tax' => $tax,
            'billing' => session('checkout.billing'),
            'shipping' => session('checkout.shipping'),
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function processPayment(Request $request, CartService $cartService, DigikopTransactionService $transactionService)
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

        $request->validate([
            'source_of_fund' => 'required|string|',
            'payment_type' => 'required|string|',
            'cart' => 'required|array|min:1',
        ]);

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
                'status' => OrderStatusEnum::CREATED->value,
                'account_no' => auth()->user()->userProfile->bank_account['nomor_rekening'] ?? '', // This would need to be set based on your business logic
                'account_bank' => auth()->user()->userProfile->bank_account['nomor_rekening'] ?? '', // This would need to be set based on your business logic
                'payment_type' => $request->payment_type, // todo! no other payment available currently, subjects to change
                'payment_method' => 'mandiri',
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
            try {
                return Inertia::render('ecommerce/paytest', [
                    'orderId' => $order->id,
                    'snapToken' => $snapToken,
                    'transaction_number' => $order->transaction_number,
                    'order' => $order
                ]);

            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            // Clear cart and checkout data from session after successful payment
            // Log::debug("message", session('cart'));
            // // session()->forget(['cart', 'checkout.billing', 'checkout.shipping']);

            // \DB::commit();

            // // Redirect to order confirmation page
            // return redirect()->route('order.complete', $order->id)->with('success', 'Order placed successfully!');
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

    public function orderComplete(Order $order)
    {
        // Ensure the order belongs to the authenticated user
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        // Load order items with product relationship
        $order->load('orderItems.product');

        return Inertia::render('ecommerce/order-completed', [
            'order' => (new OrderResource($order))->toArray(request()),
        ]);
    }
}
