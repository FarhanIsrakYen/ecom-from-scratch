<?php

namespace App\Listeners;

use App\Enums\RoleEnum;
use App\Events\LowStockDetected;
use App\Listeners\Concerns\ConfiguresQueuedListener;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAdminLowStock implements ShouldQueue
{
    use ConfiguresQueuedListener;
    use InteractsWithQueue;

    public function handle(LowStockDetected $event): void
    {
        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                RoleEnum::Admin->value,
                RoleEnum::SuperAdmin->value,
            ]))
            ->each(fn (User $user) => $user->notify(new LowStockNotification($event->inventory)));
    }
}
