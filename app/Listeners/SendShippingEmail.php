<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Events\OrderShipped;
use App\Listeners\Concerns\ConfiguresQueuedListener;
use App\Notifications\OrderStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendShippingEmail implements ShouldQueue
{
    use ConfiguresQueuedListener;
    use InteractsWithQueue;

    public function handle(OrderShipped|OrderDelivered $event): void
    {
        $type = $event instanceof OrderDelivered ? 'delivered' : 'shipped';

        $event->order->user?->notify(new OrderStatusNotification($event->order, $type));
    }
}
