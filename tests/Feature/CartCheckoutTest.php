<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_add_product_variant_to_cart(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 49.99, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');

        $this->withToken($user->createToken('phpunit')->plainTextToken)
            ->postJson('/api/cart/items', [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_id', $variant->product_id)
            ->assertJsonPath('data.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', '49.99')
            ->assertJsonPath('data.summary.subtotal', '99.98');
    }

    public function test_customer_can_update_cart_quantity(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 25, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 10, 'Initial stock');
        $token = $user->createToken('phpunit')->plainTextToken;

        $itemId = $this->withToken($token)
            ->postJson('/api/cart/items', [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->json('data.items.0.id');

        $this->withToken($token)
            ->putJson("/api/cart/items/{$itemId}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.summary.subtotal', '100.00');
    }

    public function test_customer_can_remove_cart_item(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 15, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');
        $token = $user->createToken('phpunit')->plainTextToken;

        $itemId = $this->withToken($token)
            ->postJson('/api/cart/items', [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->json('data.items.0.id');

        $this->withToken($token)
            ->deleteJson("/api/cart/items/{$itemId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.summary.subtotal', '0.00');

        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
    }

    public function test_customer_can_clear_cart(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $firstVariant = ProductVariant::factory()->create(['price' => 10, 'stock' => 0]);
        $secondVariant = ProductVariant::factory()->create(['price' => 20, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($firstVariant->product_id, $firstVariant->id, 5, 'Initial stock');
        app(InventoryService::class)->increaseStock($secondVariant->product_id, $secondVariant->id, 5, 'Initial stock');
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $firstVariant->product_id,
            'product_variant_id' => $firstVariant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $secondVariant->product_id,
            'product_variant_id' => $secondVariant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->withToken($token)
            ->deleteJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.summary.total', '0.00');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_success_reserves_stock_creates_order_and_clears_cart(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 30, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $orderId = $this->withToken($token)
            ->postJson('/api/checkout', $this->checkoutPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.subtotal', '60.00')
            ->assertJsonPath('data.delivery_charge', '0.00')
            ->assertJsonPath('data.total', '60.00')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->json('data.id');

        $this->assertDatabaseHas('inventories', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'available_stock' => 3,
            'reserved_stock' => 2,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'type' => 'reserved',
            'quantity' => 2,
            'reference_type' => 'order',
            'reference_id' => $orderId,
        ]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_fails_when_stock_is_unavailable(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 10, 'stock' => 0]);
        $inventory = app(InventoryService::class);
        $inventory->increaseStock($variant->product_id, $variant->id, 2, 'Initial stock');
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $inventory->decreaseStock($variant->product_id, $variant->id, 2, 'External stock usage');

        $this->withToken($token)
            ->postJson('/api/checkout', $this->checkoutPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_fails_when_cart_product_is_no_longer_purchasable(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 10, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 2, 'Initial stock');
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $variant->product->update(['status' => 'inactive']);

        $this->withToken($token)
            ->postJson('/api/checkout', $this->checkoutPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Product is not available for purchase.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_uses_latest_product_price_from_database(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 20, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $variant->update(['price' => 35]);

        $this->withToken($token)
            ->postJson('/api/checkout', $this->checkoutPayload())
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '35.00')
            ->assertJsonPath('data.subtotal', '70.00')
            ->assertJsonPath('data.total', '70.00');
    }

    private function checkoutPayload(array $overrides = []): array
    {
        return $overrides + [
            'shipping_address' => [
                'name' => 'Jane Customer',
                'phone' => '+15555550123',
                'address_line_1' => '100 Market Street',
                'address_line_2' => null,
                'city' => 'Dhaka',
                'state' => 'Dhaka',
                'postal_code' => '1207',
                'country' => 'Bangladesh',
            ],
        ];
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
