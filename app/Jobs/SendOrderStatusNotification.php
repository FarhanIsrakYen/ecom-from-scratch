<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderStatusNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly int $orderId,
        private readonly string $type,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $order = Order::query()->with('user')->find($this->orderId);

        if ($order === null || $order->user === null) {
            return;
        }

        $order->user->notify(new OrderStatusNotification($order, $this->type));
    }
}
