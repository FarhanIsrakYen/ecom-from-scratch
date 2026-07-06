<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Listeners\Concerns\ConfiguresQueuedListener;
use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentSuccessEmail implements ShouldQueue
{
    use ConfiguresQueuedListener;
    use InteractsWithQueue;

    public function handle(OrderPaid $event): void
    {
        $event->order->user?->notify(new OrderStatusNotification($event->order, 'paid'));
    }
}
