<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_product_detail_cache_is_invalidated_when_product_is_updated(): void
    {
        $product = Product::factory()->create(['name' => 'Original Shoe']);

        $this->getJson('/api/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.name', 'Original Shoe');

        $product->update(['name' => 'Updated Shoe']);

        $this->getJson('/api/products/'.$product->fresh()->slug)
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Shoe');
    }

    public function test_public_product_listing_cache_is_invalidated_when_product_is_created_or_deleted(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $product = Product::factory()->create(['name' => 'Cached Listing Shoe']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cached Listing Shoe');

        $product->delete();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_category_tree_and_brand_list_caches_are_invalidated_on_changes(): void
    {
        Category::factory()->create(['name' => 'Visible Category']);
        Brand::factory()->create(['name' => 'Visible Brand']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/brands')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Category::factory()->create(['name' => 'New Category']);
        Brand::factory()->create(['name' => 'New Brand']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/brands')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_product_detail_cache_is_invalidated_when_inventory_changes(): void
    {
        $product = Product::factory()->create(['name' => 'Inventory Shoe']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'available_stock' => 5,
        ]);

        $this->getJson('/api/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.variants.0.inventory.available_stock', 5);

        app(InventoryService::class)->increaseStock($product->id, $variant->id, 3, 'Restock');

        $this->getJson('/api/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.variants.0.inventory.available_stock', 8);
    }
}
