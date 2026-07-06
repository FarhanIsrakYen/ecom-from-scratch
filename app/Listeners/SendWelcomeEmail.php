<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Listeners\Concerns\ConfiguresQueuedListener;
use App\Notifications\WelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendWelcomeEmail implements ShouldQueue
{
    use ConfiguresQueuedListener;
    use InteractsWithQueue;

    public function handle(UserRegistered $event): void
    {
        $event->user->notify(new WelcomeNotification);
    }
}
