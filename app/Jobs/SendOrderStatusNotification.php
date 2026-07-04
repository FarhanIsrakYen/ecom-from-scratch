<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderStatusNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $orderId,
        private readonly string $type,
    ) {}

    public function handle(): void
    {
        $order = Order::query()->with('user')->find($this->orderId);

        if ($order === null || $order->user === null) {
            return;
        }

        $order->user->notify(new OrderStatusNotification($order, $this->type));
    }
}
