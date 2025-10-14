<?php

namespace App\Jobs;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The order instance.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Re-fetch the order from the database to get the latest status.
        // This is important in case the status was updated (e.g., to 'paid')
        // between the time the job was dispatched and when it runs.
        $order = $this->order->fresh();

        // Check if the order status is still 'pending'
        if ($order->status === OrderStatusEnum::PENDING->value) {
            // Update the status to 'expired'
            $order->status = OrderStatusEnum::EXPIRED->value;
            $order->save();

            Log::info("Order #{$order->transaction_number} has been marked as expired.");

            // Optional: You could also add logic here to restore product stock, etc.
        } else {
            Log::info("Order #{$order->transaction_number} was not expired because its status was already '{$order->status}'.");
        }
    }
}