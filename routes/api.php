<?php

use App\Http\Controllers\Api\V1\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\DeliveryZoneController as AdminDeliveryZoneController;
use App\Http\Controllers\Api\V1\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Api\V1\Admin\KnowledgeBaseController as AdminKnowledgeBaseController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Api\V1\Admin\ProductVariantController as AdminProductVariantController;
use App\Http\Controllers\Api\V1\Admin\SystemLogController as AdminSystemLogController;
use App\Http\Controllers\Api\V1\Admin\TaxSettingController as AdminTaxSettingController;
use App\Http\Controllers\Api\V1\AIProductSearchController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CustomerOrderController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductSearchController;
use App\Http\Controllers\Api\V1\ShoppingAssistantController;
use App\Http\Controllers\Api\V1\StripePaymentController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use Illuminate\Support\Facades\Route;

$registerApiRoutes = function (?string $verificationRouteName = null): void {
    Route::get('health', HealthController::class);
    Route::post('ai/product-search', AIProductSearchController::class)->middleware('throttle:ai-search');
    Route::post('ai/assistant', ShoppingAssistantController::class)->middleware('throttle:ai-search');
    Route::get('products', [ProductController::class, 'index'])->middleware('throttle:product-search');
    Route::get('products/search/suggestions', [ProductSearchController::class, 'suggestions'])->middleware('throttle:product-search');
    Route::get('products/search/popular', [ProductSearchController::class, 'popular'])->middleware('throttle:product-search');
    Route::get('products/{slug}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('brands', [BrandController::class, 'index']);
    Route::post('stripe/webhook', StripeWebhookController::class);

    Route::prefix('auth')->group(function () use ($verificationRouteName): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:login');

        $verificationRoute = Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed');

        if ($verificationRouteName !== null) {
            $verificationRoute->name($verificationRouteName);
        }

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('super-admin/health', HealthController::class)->middleware('role:super_admin');
        Route::get('admin/health', HealthController::class)->middleware('role:admin');
        Route::get('customer/health', HealthController::class)->middleware('role:customer');

        Route::get('cart', [CartController::class, 'show']);
        Route::get('products/search/history', [ProductSearchController::class, 'history'])->middleware('throttle:product-search');
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('cart/items', [CartController::class, 'store']);
        Route::put('cart/items/{item}', [CartController::class, 'update']);
        Route::delete('cart/items/{item}', [CartController::class, 'destroy']);
        Route::delete('cart', [CartController::class, 'clear']);
        Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');
        Route::get('orders', [CustomerOrderController::class, 'index']);
        Route::get('orders/{order}', [CustomerOrderController::class, 'show']);
        Route::post('payments/stripe/checkout-sessions', [StripePaymentController::class, 'createCheckoutSession']);

        Route::prefix('admin')
            ->middleware('role:super_admin,admin')
            ->group(function (): void {
                Route::apiResource('categories', AdminCategoryController::class);
                Route::apiResource('brands', AdminBrandController::class);
                Route::apiResource('products', AdminProductController::class);
                Route::apiResource('variants', AdminProductVariantController::class);
                Route::apiResource('images', AdminProductImageController::class);
                Route::apiResource('coupons', AdminCouponController::class);
                Route::apiResource('delivery-zones', AdminDeliveryZoneController::class);
                Route::apiResource('tax-settings', AdminTaxSettingController::class);
                Route::get('dashboard', AdminDashboardController::class);
                Route::get('orders', [AdminOrderController::class, 'index']);
                Route::get('orders/{order}', [AdminOrderController::class, 'show']);
                Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
                Route::get('inventory', [AdminInventoryController::class, 'index']);
                Route::post('inventory/adjustments', [AdminInventoryController::class, 'adjust']);
                Route::post('knowledge-base/sync', [AdminKnowledgeBaseController::class, 'sync']);
                Route::get('system-logs', AdminSystemLogController::class);
            });
    });
};

$registerApiRoutes('verification.verify');

Route::prefix('v1')->group(fn () => $registerApiRoutes('v1.verification.verify'));
