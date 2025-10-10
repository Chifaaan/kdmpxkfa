<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class PaymentController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    public function createTransaction(Request $request, Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        \Log::info('Creating transaction for order: ' . $order->id);


        $itemDetails = $order->orderItems->map(function ($item) {
            return [
                'id' => $item->product_id,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'name' => $item->product->name,
            ];
        })->toArray();

        $payload = [
            'transaction_details' => [
                'order_id' => $order->id,
                'gross_amount' => $order->total_price,
            ],
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
            ],
            'item_details' => $itemDetails,
        ];

        try {
            $snapToken = Snap::getSnapToken($payload);
            return response()->json(['snap_token' => $snapToken]);
        } catch (\Exception $e) {
            \Log::error('Midtrans Snap Token generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate payment token.'], 500);
        }
    }

    public function handleNotification(Request $request)
    {
        $notification = new Notification();

        $transactionStatus = $notification->transaction_status;
        $orderId = $notification->order_id;
        $fraudStatus = $notification->fraud_status;

        $order = Order::findOrFail($orderId);

        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'accept') {
                // TODO set your transaction success logic here
                $order->payment_status = 'paid';
                $order->status = 'processing';
            }
        } else if ($transactionStatus == 'settlement') {
            // TODO set your transaction success logic here
            $order->payment_status = 'paid';
            $order->status = 'processing';
        } else if ($transactionStatus == 'pending') {
            // TODO set your transaction pending logic here
            $order->payment_status = 'pending';
        } else if ($transactionStatus == 'deny') {
            // TODO set your transaction denied logic here
            $order->payment_status = 'failed';
        } else if ($transactionStatus == 'expire') {
            // TODO set your transaction expired logic here
            $order->payment_status = 'expired';
        } else if ($transactionStatus == 'cancel') {
            // TODO set your transaction cancelled logic here
            $order->payment_status = 'failed';
        }

        $order->transaction_id = $notification->transaction_id;
        $order->save();

        return response()->json(['status' => 'ok']);
    }
}