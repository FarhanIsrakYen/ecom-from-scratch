<?php

namespace App\Providers;

use App\Events\UserRegistered;
use App\Listeners\SendWelcomeNotification;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(UserRegistered::class, SendWelcomeNotification::class);

        ResetPasswordNotification::createUrlUsing(function (User $user, string $token): string {
            $baseUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return $baseUrl.'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });
    }
}
