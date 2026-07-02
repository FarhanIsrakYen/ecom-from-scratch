<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Events\LowStockDetected;
use App\Exceptions\InsufficientStockException;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_order_more_than_available_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        $inventory = app(InventoryService::class);
        $reservations = app(StockReservationService::class);

        $inventory->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');

        $reservations->reserve($variant->product_id, $variant->id, 5, 'checkout', 1001);

        $this->expectException(InsufficientStockException::class);

        $reservations->reserve($variant->product_id, $variant->id, 1, 'checkout', 1002);
    }

    public function test_stock_is_reserved_during_checkout(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 10, 'Initial stock');

        $inventory = app(StockReservationService::class)
            ->reserve($variant->product_id, $variant->id, 3, 'checkout', 5001);

        $this->assertSame(7, $inventory->available_stock);
        $this->assertSame(3, $inventory->reserved_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'type' => 'reserved',
            'quantity' => 3,
            'reference_type' => 'checkout',
            'reference_id' => 5001,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 7,
        ]);
    }

    public function test_race_condition_handling_rechecks_available_stock_inside_locked_transaction(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');

        $reservations = app(StockReservationService::class);
        $reservations->reserve($variant->product_id, $variant->id, 4, 'checkout', 5101);

        try {
            $reservations->reserve($variant->product_id, $variant->id, 2, 'checkout', 5102);
            $this->fail('The second reservation should not oversell stock already reserved by the first transaction.');
        } catch (InsufficientStockException) {
            $this->assertDatabaseHas('inventories', [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'available_stock' => 1,
                'reserved_stock' => 4,
            ]);
        }
    }

    public function test_stock_is_released_after_payment_failure(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 8, 'Initial stock');

        $reservations = app(StockReservationService::class);
        $reservations->reserve($variant->product_id, $variant->id, 4, 'checkout', 6001);
        $inventory = $reservations->release($variant->product_id, $variant->id, 4, 'payment', 7001);

        $this->assertSame(8, $inventory->available_stock);
        $this->assertSame(0, $inventory->reserved_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'type' => 'released',
            'quantity' => 4,
            'reference_type' => 'payment',
            'reference_id' => 7001,
        ]);
    }

    public function test_reserved_stock_is_confirmed_sold_after_payment_success(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 6, 'Initial stock');

        $reservations = app(StockReservationService::class);
        $reservations->reserve($variant->product_id, $variant->id, 2, 'checkout', 8001);
        $inventory = $reservations->confirmSold($variant->product_id, $variant->id, 2, 'payment', 9001);

        $this->assertSame(4, $inventory->available_stock);
        $this->assertSame(0, $inventory->reserved_stock);
        $this->assertSame(2, $inventory->sold_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'type' => 'sold',
            'quantity' => 2,
            'reference_id' => 9001,
        ]);
    }

    public function test_low_stock_event_is_dispatched_when_stock_falls_below_threshold(): void
    {
        Event::fake([LowStockDetected::class]);

        $variant = ProductVariant::factory()->create(['stock' => 0]);
        $inventory = app(InventoryService::class);
        $inventory->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');
        $inventory->updateLowStockThreshold($variant->product_id, $variant->id, 3);

        app(StockReservationService::class)->reserve($variant->product_id, $variant->id, 3, 'checkout', 111);

        Event::assertDispatched(LowStockDetected::class, fn (LowStockDetected $event): bool => $event->inventory->available_stock === 2);
    }

    public function test_admin_can_adjust_inventory_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        $token = $this->adminToken();

        $this->withToken($token)
            ->postJson('/api/admin/inventory/adjustments', [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'type' => 'stock_in',
                'quantity' => 12,
                'reason' => 'Purchase order received',
                'low_stock_threshold' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('data.available_stock', 12)
            ->assertJsonPath('data.low_stock_threshold', 4);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'type' => 'stock_in',
            'quantity' => 12,
            'reason' => 'Purchase order received',
        ]);
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
