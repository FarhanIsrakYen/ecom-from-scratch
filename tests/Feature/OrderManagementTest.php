<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Events\OrderDelivered;
use App\Events\OrderProcessingStarted;
use App\Events\OrderShipped;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_filter_and_view_orders(): void
    {
        Queue::fake();
        $admin = $this->userWithRole(RoleEnum::Admin);
        $alice = $this->userWithRole(RoleEnum::Customer, ['name' => 'Alice Buyer']);
        $bob = $this->userWithRole(RoleEnum::Customer, ['name' => 'Bob Buyer']);
        $aliceOrder = $this->createCheckoutOrder($alice, 1, 30);
        $bobOrder = $this->createCheckoutOrder($bob, 1, 45);

        $aliceOrder->update([
            'status' => 'paid',
            'payment_status' => 'paid',
            'created_at' => now()->subDay(),
        ]);
        $bobOrder->update(['status' => 'processing']);

        Sanctum::actingAs($admin);

        $this
            ->getJson('/api/admin/orders?status=paid&payment_status=paid&customer=Alice&from_date='.now()->subDays(2)->toDateString().'&to_date='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $aliceOrder->id)
            ->assertJsonPath('data.0.customer.name', 'Alice Buyer');

        $this
            ->getJson("/api/admin/orders/{$aliceOrder->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $aliceOrder->id)
            ->assertJsonPath('data.items.0.quantity', 1);
    }

    public function test_admin_can_advance_order_status_create_shipment_and_write_audits(): void
    {
        Event::fake([OrderProcessingStarted::class, OrderShipped::class, OrderDelivered::class]);
        Queue::fake();
        $admin = $this->userWithRole(RoleEnum::Admin);
        $customer = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createCheckoutOrder($customer, 1, 30);
        $order->update(['status' => 'paid', 'payment_status' => 'paid']);
        Sanctum::actingAs($admin);

        $this
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this
            ->patchJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'shipped',
                'shipment' => [
                    'courier_name' => 'DHL',
                    'tracking_number' => 'TRK-123',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.shipments.0.status', 'shipped');

        $this
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.shipments.0.status', 'delivered');

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'courier_name' => 'DHL',
            'tracking_number' => 'TRK-123',
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('order_status_audits', [
            'order_id' => $order->id,
            'from_status' => 'paid',
            'to_status' => 'processing',
            'changed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('order_status_audits', [
            'order_id' => $order->id,
            'from_status' => 'processing',
            'to_status' => 'shipped',
        ]);
        Event::assertDispatched(OrderProcessingStarted::class);
        Event::assertDispatched(OrderShipped::class);
        Event::assertDispatched(OrderDelivered::class);
    }

    public function test_invalid_status_transitions_are_rejected(): void
    {
        Queue::fake();
        $admin = $this->userWithRole(RoleEnum::Admin);
        $customer = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createCheckoutOrder($customer, 1, 30);
        $order->update(['status' => 'paid', 'payment_status' => 'paid']);

        Sanctum::actingAs($admin);

        $this
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('order_status_audits', [
            'order_id' => $order->id,
            'to_status' => 'delivered',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_refund_status_requires_refund_flow(): void
    {
        Queue::fake();
        $admin = $this->userWithRole(RoleEnum::Admin);
        $customer = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createCheckoutOrder($customer, 1, 30);
        $order->update(['status' => 'paid', 'payment_status' => 'paid']);

        Sanctum::actingAs($admin);

        $this
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order can only be refunded through the refund flow.');
    }

    public function test_customer_can_view_only_their_order_history(): void
    {
        Queue::fake();
        $customer = $this->userWithRole(RoleEnum::Customer);
        $otherCustomer = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createCheckoutOrder($customer, 1, 30);
        $otherOrder = $this->createCheckoutOrder($otherCustomer, 1, 45);
        Sanctum::actingAs($customer);

        $this
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id);

        $this
            ->getJson("/api/orders/{$otherOrder->id}")
            ->assertNotFound();
    }

    private function createCheckoutOrder(User $user, int $quantity, float $unitPrice): Order
    {
        $variant = ProductVariant::factory()->create(['price' => $unitPrice, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ])->assertCreated();

        $orderId = $this->postJson('/api/checkout', [
            'shipping_address' => [
                'name' => 'Jane Customer',
                'phone' => '+15555550123',
                'address_line_1' => '100 Market Street',
                'city' => 'Dhaka',
                'country' => 'Bangladesh',
            ],
        ])
            ->assertCreated()
            ->json('data.id');

        return Order::query()->with('items')->findOrFail($orderId);
    }

    private function userWithRole(RoleEnum $role, array $attributes = []): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role->value],
            ['label' => $role->label()],
        );

        $user = User::factory()->create($attributes);
        $user->roles()->attach($roleModel);

        return $user;
    }
}
