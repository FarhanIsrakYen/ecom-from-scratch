<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_can_be_searched_filtered_sorted_and_faceted(): void
    {
        $shoes = Category::factory()->create(['name' => 'Shoes']);
        $shirts = Category::factory()->create(['name' => 'Shirts']);
        $acme = Brand::factory()->create(['name' => 'Acme']);
        $north = Brand::factory()->create(['name' => 'North']);

        $runner = Product::factory()->create([
            'name' => 'Trail Running Shoe',
            'description' => 'Grip for mountain running',
            'category_id' => $shoes->id,
            'brand_id' => $acme->id,
            'base_price' => 120,
            'average_rating' => 4.7,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $runner->id,
            'attributes' => ['color' => 'red', 'size' => '42'],
        ]);
        Inventory::factory()->create([
            'product_id' => $runner->id,
            'available_stock' => 8,
            'sold_stock' => 50,
        ]);

        $shirt = Product::factory()->create([
            'name' => 'Running Shirt',
            'description' => 'Lightweight running layer',
            'category_id' => $shirts->id,
            'brand_id' => $north->id,
            'base_price' => 45,
            'average_rating' => 4.1,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $shirt->id,
            'attributes' => ['color' => 'blue', 'size' => 'M'],
        ]);
        Inventory::factory()->create([
            'product_id' => $shirt->id,
            'available_stock' => 0,
            'sold_stock' => 5,
        ]);

        Product::factory()->create([
            'name' => 'Casual Sneaker',
            'category_id' => $shoes->id,
            'brand_id' => $acme->id,
            'base_price' => 70,
            'average_rating' => 3.9,
        ]);

        $this->getJson('/api/products?q=running&category=shoes&brand=acme&min_price=100&max_price=150&attributes[color]=red&availability=in_stock&min_rating=4&sort=most_popular')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Trail Running Shoe')
            ->assertJsonPath('meta.facets.brands.0.slug', 'acme')
            ->assertJsonPath('meta.facets.categories.0.slug', 'shoes')
            ->assertJsonPath('meta.facets.attributes.color.0.value', 'red')
            ->assertJsonStructure([
                'meta' => [
                    'facets' => ['brands', 'categories', 'price_ranges', 'attributes'],
                    'suggestions',
                    'popular_searches',
                ],
            ]);
    }

    public function test_search_suggestions_history_and_popular_searches_are_recorded(): void
    {
        Product::factory()->create(['name' => 'Leather Backpack', 'description' => 'Daily carry bag']);

        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/products?q=backpack')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Leather Backpack');

        $this->withToken($token)
            ->getJson('/api/products/search/history')
            ->assertOk()
            ->assertJsonPath('data.0.query', 'backpack');

        $this->getJson('/api/products/search/popular')
            ->assertOk()
            ->assertJsonPath('data.0.query', 'backpack')
            ->assertJsonPath('data.0.search_count', 1);

        $this->getJson('/api/products/search/suggestions?q=back')
            ->assertOk()
            ->assertJsonFragment(['backpack']);
    }

    public function test_search_can_sort_by_price_and_latest(): void
    {
        Product::factory()->create([
            'name' => 'Searchable Budget Camera',
            'base_price' => 100,
            'created_at' => now()->subDay(),
        ]);
        Product::factory()->create([
            'name' => 'Searchable Pro Camera',
            'base_price' => 900,
            'created_at' => now(),
        ]);

        $this->getJson('/api/products?q=searchable&sort=price_low_to_high')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Searchable Budget Camera');

        $this->getJson('/api/products?q=searchable&sort=price_high_to_low')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Searchable Pro Camera');

        $this->getJson('/api/products?q=searchable&sort=latest')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Searchable Pro Camera');
    }
}
