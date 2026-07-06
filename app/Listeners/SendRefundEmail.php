<?php

namespace App\Listeners;

use App\Events\OrderRefunded;
use App\Listeners\Concerns\ConfiguresQueuedListener;
use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRefundEmail implements ShouldQueue
{
    use ConfiguresQueuedListener;
    use InteractsWithQueue;

    public function handle(OrderRefunded $event): void
    {
        $event->order->user?->notify(new OrderStatusNotification($event->order, 'refunded'));
    }
}
