<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Listeners\Concerns\ConfiguresQueuedListener;
use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendCancellationEmail implements ShouldQueue
{
    use ConfiguresQueuedListener;
    use InteractsWithQueue;

    public function handle(OrderCancelled $event): void
    {
        $event->order->user?->notify(new OrderStatusNotification($event->order, 'cancelled'));
    }
}
