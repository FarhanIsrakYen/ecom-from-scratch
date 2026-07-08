<?php

namespace App\Providers;

use App\Events\LowStockDetected;
use App\Events\OrderCancelled;
use App\Events\OrderDelivered;
use App\Events\OrderPaid;
use App\Events\OrderPlaced;
use App\Events\OrderRefunded;
use App\Events\OrderShipped;
use App\Events\UserRegistered;
use App\Listeners\NotifyAdminLowStock;
use App\Listeners\SendCancellationEmail;
use App\Listeners\SendOrderPlacedEmail;
use App\Listeners\SendPaymentSuccessEmail;
use App\Listeners\SendRefundEmail;
use App\Listeners\SendShippingEmail;
use App\Listeners\SendWelcomeEmail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Observers\CatalogCacheObserver;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\MockAIProvider;
use App\Services\AI\OpenAIProvider;
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
        $this->app->bind(AIProviderInterface::class, function () {
            return config('services.ai.provider') === 'mock'
                ? new MockAIProvider
                : new OpenAIProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(UserRegistered::class, SendWelcomeEmail::class);
        Event::listen(OrderPlaced::class, SendOrderPlacedEmail::class);
        Event::listen(OrderPaid::class, SendPaymentSuccessEmail::class);
        Event::listen(OrderShipped::class, SendShippingEmail::class);
        Event::listen(OrderDelivered::class, SendShippingEmail::class);
        Event::listen(OrderCancelled::class, SendCancellationEmail::class);
        Event::listen(OrderRefunded::class, SendRefundEmail::class);
        Event::listen(LowStockDetected::class, NotifyAdminLowStock::class);

        Brand::observe(CatalogCacheObserver::class);
        Category::observe(CatalogCacheObserver::class);
        Inventory::observe(CatalogCacheObserver::class);
        Product::observe(CatalogCacheObserver::class);
        ProductImage::observe(CatalogCacheObserver::class);
        ProductVariant::observe(CatalogCacheObserver::class);

        ResetPasswordNotification::createUrlUsing(function (User $user, string $token): string {
            $baseUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return $baseUrl.'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });
    }
}
