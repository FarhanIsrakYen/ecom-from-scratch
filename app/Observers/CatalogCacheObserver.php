<?php

namespace App\Observers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\CategoryCacheService;
use App\Services\ProductCacheService;
use Illuminate\Database\Eloquent\Model;

class CatalogCacheObserver
{
    public function saved(Model $model): void
    {
        $this->flushFor($model);
    }

    public function deleted(Model $model): void
    {
        $this->flushFor($model);
    }

    private function flushFor(Model $model): void
    {
        if ($model instanceof Category) {
            app(CategoryCacheService::class)->flush();
            app(ProductCacheService::class)->flushProducts();

            return;
        }

        if ($model instanceof Brand) {
            app(ProductCacheService::class)->flushBrands();

            return;
        }

        if (
            $model instanceof Product
            || $model instanceof ProductVariant
            || $model instanceof ProductImage
            || $model instanceof Inventory
        ) {
            app(ProductCacheService::class)->flushProducts();
        }
    }
}
