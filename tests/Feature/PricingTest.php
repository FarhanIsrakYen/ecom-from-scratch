<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_applies_coupon_delivery_charge_and_tax_from_backend(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 100, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');
        Coupon::query()->create([
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => 10,
            'max_discount_amount' => 15,
            'minimum_order_amount' => 50,
            'usage_limit' => 10,
            'usage_per_user' => 1,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ]);
        DeliveryZone::query()->create([
            'name' => 'Dhaka',
            'country' => 'Bangladesh',
            'city' => 'Dhaka',
            'charge' => 8,
            'status' => 'active',
        ]);
        TaxSetting::query()->create([
            'name' => 'VAT',
            'country' => 'Bangladesh',
            'rate' => 5,
            'status' => 'active',
        ]);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->withToken($token)
            ->postJson('/api/checkout', $this->checkoutPayload([
                'coupon_code' => 'save10',
                'discount' => 999,
                'delivery_charge' => 999,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.coupon_code', 'SAVE10')
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.discount', '15.00')
            ->assertJsonPath('data.delivery_charge', '8.00')
            ->assertJsonPath('data.tax', '9.25')
            ->assertJsonPath('data.total', '202.25');

        $this->assertDatabaseHas('coupon_usages', [
            'user_id' => $user->id,
            'discount_amount' => '15.00',
        ]);
    }

    public function test_checkout_rejects_coupon_after_user_usage_limit_is_reached(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');
        Coupon::query()->create([
            'code' => 'ONCE',
            'type' => 'fixed',
            'value' => 5,
            'usage_per_user' => 1,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ]);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->withToken($token)
            ->postJson('/api/checkout', $this->checkoutPayload(['coupon_code' => 'ONCE']))
            ->assertCreated()
            ->assertJsonPath('data.discount', '5.00');

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->withToken($token)
            ->postJson('/api/checkout', $this->checkoutPayload(['coupon_code' => 'ONCE']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Coupon usage limit has been reached for this customer.');
    }

    public function test_global_delivery_charge_is_used_when_no_location_zone_matches(): void
    {
        $user = $this->userWithRole(RoleEnum::Customer);
        $variant = ProductVariant::factory()->create(['price' => 30, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 2, 'Initial stock');
        DeliveryZone::query()->create([
            'name' => 'Global',
            'charge' => 12,
            'is_default' => true,
            'status' => 'active',
        ]);
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->withToken($token)
            ->postJson('/api/checkout', $this->checkoutPayload([
                'shipping_address' => [
                    'name' => 'Jane Customer',
                    'phone' => '+15555550123',
                    'address_line_1' => '100 Market Street',
                    'city' => 'Chittagong',
                    'country' => 'Bangladesh',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.delivery_charge', '12.00')
            ->assertJsonPath('data.total', '42.00');
    }

    public function test_admin_can_manage_coupon_delivery_zone_and_tax_setting(): void
    {
        $token = $this->userWithRole(RoleEnum::Admin)->createToken('phpunit')->plainTextToken;

        $couponId = $this->withToken($token)
            ->postJson('/api/admin/coupons', [
                'code' => 'summer',
                'type' => 'fixed',
                'value' => 20,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'SUMMER')
            ->json('data.id');

        $this->withToken($token)
            ->putJson("/api/admin/coupons/{$couponId}", ['value' => 25])
            ->assertOk()
            ->assertJsonPath('data.value', '25.00');

        $zoneId = $this->withToken($token)
            ->postJson('/api/admin/delivery-zones', [
                'name' => 'Dhaka',
                'country' => 'Bangladesh',
                'city' => 'Dhaka',
                'charge' => 9,
            ])
            ->assertCreated()
            ->assertJsonPath('data.charge', '9.00')
            ->json('data.id');

        $taxId = $this->withToken($token)
            ->postJson('/api/admin/tax-settings', [
                'name' => 'VAT',
                'country' => 'Bangladesh',
                'rate' => 7.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.rate', '7.5000')
            ->json('data.id');

        $this->withToken($token)->deleteJson("/api/admin/coupons/{$couponId}")->assertOk();
        $this->withToken($token)->deleteJson("/api/admin/delivery-zones/{$zoneId}")->assertOk();
        $this->withToken($token)->deleteJson("/api/admin/tax-settings/{$taxId}")->assertOk();
    }

    private function checkoutPayload(array $overrides = []): array
    {
        $payload = [
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

        return array_replace_recursive($payload, $overrides);
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
