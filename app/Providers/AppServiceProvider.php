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
use App\Support\Monitoring\StructuredLogger;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimits();

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

        Queue::failing(function (JobFailed $event): void {
            app(StructuredLogger::class)->failedJob('Queue job failed.', [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_name' => $event->job->resolveName(),
                'exception' => $event->exception::class,
                'message' => $event->exception->getMessage(),
            ]);
        });
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(($request->user()?->id ?? $request->ip()).'|checkout'));

        RateLimiter::for('ai-search', fn (Request $request): Limit => Limit::perMinute(20)
            ->by(($request->user()?->id ?? $request->ip()).'|ai-search'));

        RateLimiter::for('product-search', fn (Request $request): Limit => Limit::perMinute(120)
            ->by(($request->user('sanctum')?->id ?? $request->ip()).'|product-search'));
    }
}
