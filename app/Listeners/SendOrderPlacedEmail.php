<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Listeners\Concerns\ConfiguresQueuedListener;
use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderPlacedEmail implements ShouldQueue
{
    use ConfiguresQueuedListener;
    use InteractsWithQueue;

    public function handle(OrderPlaced $event): void
    {
        $event->order->user?->notify(new OrderStatusNotification($event->order, 'placed'));
    }
}
