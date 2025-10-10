<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Inertia\Inertia;
use Midtrans\Config;
use Midtrans\Snap;

class PaymentController extends Controller
{
    public function generateSnapToken(Order $order)
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
                'price' => $item->price,
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
            $snapToken = Snap::getSnapToken($params);

            return Inertia::render('ecommerce/paytest', [
                'snapToken' => $snapToken,
                'order' => $order,
                'orderId' => $order->transaction_number,
                'grossAmount' => $order->total_price,
                'clientKey' => config('midtrans.client_key'),
                'isProduction' => config('midtrans.is_production'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
}
