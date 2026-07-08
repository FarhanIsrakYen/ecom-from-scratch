<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_search_products_with_natural_language(): void
    {
        config([
            'services.ai.provider' => 'mock',
            'services.ai.mock_response' => [
                'relevant' => true,
                'product_type' => 't-shirt',
                'category' => 'T-Shirts',
                'color' => 'black',
                'size' => 'XL',
                'price_min' => null,
                'price_max' => 1000,
                'brand' => 'Acme',
            ],
        ]);

        $category = Category::factory()->create(['name' => 'T-Shirts']);
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $product = Product::factory()->create([
            'name' => 'Black Cotton T-Shirt',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price' => 900,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'attributes' => ['color' => 'black', 'size' => 'XL'],
        ]);
        Inventory::factory()->create(['product_id' => $product->id, 'available_stock' => 10]);

        Product::factory()->create([
            'name' => 'White Cotton T-Shirt',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price' => 850,
        ]);

        $this->postJson('/api/ai/product-search', [
            'query' => 'Show me black t-shirts under 1000 taka in XL size',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Products retrieved from your natural language search.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Black Cotton T-Shirt')
            ->assertJsonPath('meta.extracted_filters.product_type', 't-shirt')
            ->assertJsonPath('meta.extracted_filters.color', 'black')
            ->assertJsonPath('meta.extracted_filters.size', 'XL')
            ->assertJsonPath('meta.extracted_filters.price_max', 1000)
            ->assertJsonPath('meta.search_filters.category', 't-shirts')
            ->assertJsonPath('meta.search_filters.brand', 'acme')
            ->assertJsonPath('meta.search_filters.attributes.color', 'black')
            ->assertJsonPath('meta.search_filters.attributes.size', 'XL');
    }

    public function test_irrelevant_natural_language_query_returns_polite_empty_response(): void
    {
        config([
            'services.ai.provider' => 'mock',
            'services.ai.mock_response' => [
                'relevant' => false,
                'product_type' => null,
                'category' => null,
                'color' => null,
                'size' => null,
                'price_min' => null,
                'price_max' => null,
                'brand' => null,
            ],
        ]);

        $this->postJson('/api/ai/product-search', [
            'query' => 'Write me a poem about monsoon rain',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('message', 'I can help with product searches. Try asking for a product, category, color, size, price range, or brand.');
    }

    public function test_ai_failure_falls_back_to_local_parser(): void
    {
        config([
            'services.ai.provider' => 'mock',
            'services.ai.mock_throw' => true,
        ]);

        $product = Product::factory()->create([
            'name' => 'Black T-Shirt',
            'base_price' => 700,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'attributes' => ['color' => 'black', 'size' => 'XL'],
        ]);

        $this->postJson('/api/ai/product-search', [
            'query' => 'black t-shirt under 1000 in xl',
        ])
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Black T-Shirt')
            ->assertJsonPath('meta.extracted_filters.color', 'black')
            ->assertJsonPath('meta.extracted_filters.size', 'xl')
            ->assertJsonPath('meta.extracted_filters.price_max', 1000);
    }

    public function test_ai_output_is_sanitized_before_searching(): void
    {
        config([
            'services.ai.provider' => 'mock',
            'services.ai.mock_response' => [
                'relevant' => true,
                'product_type' => 'shirt"; drop table products; --',
                'category' => null,
                'color' => 'black',
                'size' => 'XL',
                'price_min' => 0,
                'price_max' => 1000,
                'brand' => null,
            ],
        ]);

        $this->postJson('/api/ai/product-search', [
            'query' => 'malicious search',
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.extracted_filters', []);
    }
}
