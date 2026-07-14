<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithEcommerce;
use Tests\TestCase;

class ValidationAndAuthorizationTest extends TestCase
{
    use InteractsWithEcommerce;
    use RefreshDatabase;

    public function test_authentication_validation_errors_are_returned(): void
    {
        $this
            ->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_catalog_validation_errors_are_returned(): void
    {
        $this->actingAsRole(RoleEnum::Admin);

        $this
            ->postJson('/api/admin/products', [
                'name' => '',
                'sku' => '',
                'category_id' => 999,
                'base_price' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'sku', 'category_id', 'base_price']);
    }

    public function test_customer_cannot_access_admin_catalog_routes(): void
    {
        $this->actingAsRole(RoleEnum::Customer);

        $this
            ->postJson('/api/admin/products', [])
            ->assertForbidden();
    }

    public function test_cart_and_checkout_validation_errors_are_returned(): void
    {
        $user = $this->actingAsRole(RoleEnum::Customer);
        $product = Product::factory()->create();

        $this
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'quantity' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        $this
            ->withToken($user->createToken('phpunit')->plainTextToken)
            ->postJson('/api/checkout', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_address']);
    }

    public function test_payment_search_and_ai_validation_errors_are_returned(): void
    {
        $user = $this->actingAsRole(RoleEnum::Customer);

        $this
            ->withToken($user->createToken('phpunit')->plainTextToken)
            ->postJson('/api/payments/stripe/checkout-sessions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        $this
            ->getJson('/api/products?per_page=101&min_rating=6')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page', 'min_rating']);

        $this
            ->postJson('/api/ai/product-search', ['query' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_coupon_validation_and_authorization_are_enforced(): void
    {
        $this->actingAsRole(RoleEnum::Customer);

        $this
            ->postJson('/api/admin/coupons', [
                'code' => 'save10',
                'type' => 'fixed',
                'value' => 10,
            ])
            ->assertForbidden();

        $this->actingAsRole(RoleEnum::Admin);

        $this
            ->postJson('/api/admin/coupons', [
                'code' => '',
                'type' => 'bogus',
                'value' => -5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'type', 'value']);
    }
}
