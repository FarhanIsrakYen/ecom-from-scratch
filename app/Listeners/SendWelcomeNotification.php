<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeNotification implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        // Queue-backed welcome notification hook for the customer onboarding flow.
    }
}
