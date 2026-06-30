<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_categories_and_brands_return_active_records(): void
    {
        Category::factory()->create(['name' => 'Women Shoes']);
        Category::factory()->create(['name' => 'Inactive Category', 'status' => 'inactive']);
        Brand::factory()->create(['name' => 'Acme']);
        Brand::factory()->create(['name' => 'Hidden Brand', 'status' => 'inactive']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'women-shoes');

        $this->getJson('/api/brands')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'acme');
    }

    public function test_public_products_can_be_filtered_sorted_and_paginated(): void
    {
        $category = Category::factory()->create(['name' => 'Sneakers']);
        $brand = Brand::factory()->create(['name' => 'Runner']);

        Product::factory()->create([
            'name' => 'Cheap Runner',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price' => 20,
            'is_featured' => true,
        ]);
        Product::factory()->create([
            'name' => 'Premium Runner',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price' => 100,
            'is_featured' => true,
        ]);
        Product::factory()->create(['name' => 'Inactive Product', 'status' => 'inactive']);

        $this->getJson('/api/products?category=sneakers&brand=runner&featured=1&min_price=10&max_price=120&sort=price_high_to_low&per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Premium Runner')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.has_more_pages', true)
            ->assertJsonStructure(['meta' => ['next_cursor']]);
    }

    public function test_public_product_details_are_returned_by_slug_with_relations(): void
    {
        $product = Product::factory()->create(['name' => 'Trail Shoe']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'TRAIL-BLACK-42']);
        ProductImage::factory()->create(['product_id' => $product->id, 'is_primary' => true]);

        $this->getJson('/api/products/trail-shoe')
            ->assertOk()
            ->assertJsonPath('data.slug', 'trail-shoe')
            ->assertJsonPath('data.variants.0.sku', 'TRAIL-BLACK-42')
            ->assertJsonCount(1, 'data.images');
    }

    public function test_admin_can_create_update_and_delete_category_and_brand(): void
    {
        $token = $this->adminToken();

        $categoryId = $this->withToken($token)
            ->postJson('/api/admin/categories', [
                'name' => 'Electronics',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'electronics')
            ->json('data.id');

        $this->withToken($token)
            ->putJson("/api/admin/categories/{$categoryId}", ['name' => 'Smart Electronics'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'smart-electronics');

        $this->withToken($token)
            ->getJson("/api/admin/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Smart Electronics');

        $brandId = $this->withToken($token)
            ->postJson('/api/admin/brands', [
                'name' => 'Northwind',
                'logo' => 'brands/northwind.png',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'northwind')
            ->json('data.id');

        $this->withToken($token)
            ->getJson("/api/admin/brands/{$brandId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Northwind');

        $this->withToken($token)
            ->deleteJson("/api/admin/brands/{$brandId}")
            ->assertOk();

        $this->assertDatabaseMissing('brands', ['id' => $brandId]);
    }

    public function test_admin_can_create_update_and_delete_products_with_variants_and_images(): void
    {
        $token = $this->adminToken();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $productId = $this->withToken($token)
            ->postJson('/api/admin/products', [
                'name' => 'Classic Tee',
                'description' => 'Cotton tee',
                'short_description' => 'Soft tee',
                'sku' => 'TEE-BASE',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'base_price' => 30,
                'sale_price' => 25,
                'status' => 'active',
                'is_featured' => true,
                'variants' => [
                    [
                        'sku' => 'TEE-BLK-M',
                        'attributes' => ['color' => 'black', 'size' => 'M'],
                        'price' => 25,
                        'stock' => 12,
                    ],
                ],
                'images' => [
                    [
                        'image_path' => 'products/classic-tee.jpg',
                        'is_primary' => true,
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'classic-tee')
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonCount(1, 'data.images')
            ->json('data.id');

        $this->withToken($token)
            ->putJson("/api/admin/products/{$productId}", ['name' => 'Classic Cotton Tee', 'base_price' => 32])
            ->assertOk()
            ->assertJsonPath('data.slug', 'classic-cotton-tee')
            ->assertJsonPath('data.base_price', '32.00');

        $this->withToken($token)
            ->getJson('/api/admin/products?status=active')
            ->assertOk()
            ->assertJsonPath('meta.has_more_pages', false)
            ->assertJsonPath('meta.next_cursor', null);

        $this->withToken($token)
            ->getJson("/api/admin/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Classic Cotton Tee');

        $this->withToken($token)
            ->deleteJson("/api/admin/products/{$productId}")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $productId]);
    }

    public function test_admin_can_create_update_and_delete_variants_and_images(): void
    {
        $token = $this->adminToken();
        $product = Product::factory()->create();

        $variantId = $this->withToken($token)
            ->postJson('/api/admin/variants', [
                'product_id' => $product->id,
                'sku' => 'VAR-RED-L',
                'attributes' => ['color' => 'red', 'size' => 'L'],
                'price' => 40,
                'stock' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.stock', 5)
            ->json('data.id');

        $this->withToken($token)
            ->putJson("/api/admin/variants/{$variantId}", ['stock' => 7])
            ->assertOk()
            ->assertJsonPath('data.stock', 7);

        $this->withToken($token)
            ->getJson("/api/admin/variants/{$variantId}")
            ->assertOk()
            ->assertJsonPath('data.sku', 'VAR-RED-L');

        $imageId = $this->withToken($token)
            ->postJson('/api/admin/images', [
                'product_id' => $product->id,
                'image_path' => 'products/red.jpg',
                'is_primary' => true,
                'sort_order' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_primary', true)
            ->json('data.id');

        $this->withToken($token)
            ->putJson("/api/admin/images/{$imageId}", ['sort_order' => 3])
            ->assertOk()
            ->assertJsonPath('data.sort_order', 3);

        $this->withToken($token)
            ->getJson('/api/admin/images?product_id='.$product->id)
            ->assertOk()
            ->assertJsonPath('meta.has_more_pages', false)
            ->assertJsonPath('meta.next_cursor', null);

        $this->withToken($token)
            ->getJson("/api/admin/images/{$imageId}")
            ->assertOk()
            ->assertJsonPath('data.image_path', 'products/red.jpg');

        $this->withToken($token)->deleteJson("/api/admin/variants/{$variantId}")->assertOk();
        $this->withToken($token)->deleteJson("/api/admin/images/{$imageId}")->assertOk();

        $this->assertDatabaseMissing('product_variants', ['id' => $variantId]);
        $this->assertDatabaseMissing('product_images', ['id' => $imageId]);
    }

    public function test_customer_cannot_access_admin_catalog_endpoints(): void
    {
        $customer = $this->userWithRole(RoleEnum::Customer);

        $this->withToken($customer->createToken('phpunit')->plainTextToken)
            ->postJson('/api/admin/categories', ['name' => 'Forbidden'])
            ->assertForbidden();
    }

    private function adminToken(): string
    {
        return $this->userWithRole(RoleEnum::Admin)->createToken('phpunit')->plainTextToken;
    }

    private function userWithRole(RoleEnum $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role->value],
            ['label' => $role->label()],
        );

        $user = User::factory()->create();
        $user->roles()->attach($roleModel);

        return $user;
    }
}
