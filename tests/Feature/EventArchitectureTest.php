<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Events\LowStockDetected;
use App\Events\OrderPlaced;
use App\Events\OrderShipped;
use App\Events\UserRegistered;
use App\Listeners\NotifyAdminLowStock;
use App\Listeners\SendOrderPlacedEmail;
use App\Listeners\SendShippingEmail;
use App\Listeners\SendWelcomeEmail;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Services\InventoryService;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_dispatches_user_registered_and_queues_welcome_listener(): void
    {
        Queue::fake();
        Event::fake([UserRegistered::class]);
        $this->createRole(RoleEnum::Customer);

        $this->postJson('/api/auth/register', [
            'name' => 'Jane Customer',
            'email' => 'event-jane@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'device_name' => 'phpunit',
        ])->assertCreated();

        Event::assertDispatched(UserRegistered::class);

        Event::fakeExcept([UserRegistered::class]);
        UserRegistered::dispatch(User::query()->where('email', 'event-jane@example.com')->firstOrFail());

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === SendWelcomeEmail::class);
    }

    public function test_checkout_dispatches_order_placed_and_queues_order_listener(): void
    {
        Queue::fake();
        Event::fake([OrderPlaced::class]);
        $customer = $this->userWithRole(RoleEnum::Customer);

        $this->createCheckoutOrder($customer, 1, 20);

        Event::assertDispatched(OrderPlaced::class);

        Event::fakeExcept([OrderPlaced::class]);
        OrderPlaced::dispatch(Order::query()->with('user')->firstOrFail());

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === SendOrderPlacedEmail::class);
    }

    public function test_order_shipping_event_queues_shipping_listener(): void
    {
        Queue::fake();
        $customer = $this->userWithRole(RoleEnum::Customer);
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-TEST-SHIPPING',
            'status' => 'shipped',
            'payment_status' => 'paid',
            'subtotal' => 20,
            'discount' => 0,
            'delivery_charge' => 0,
            'tax' => 0,
            'total' => 20,
        ]);

        OrderShipped::dispatch($order);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === SendShippingEmail::class);
    }

    public function test_low_stock_event_queues_admin_notification_listener(): void
    {
        Queue::fake();
        $this->userWithRole(RoleEnum::Admin);
        $variant = ProductVariant::factory()->create(['stock' => 0]);
        $inventory = app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 3, 'Initial stock');
        app(InventoryService::class)->updateLowStockThreshold($inventory->product_id, $inventory->product_variant_id, 5);

        LowStockDetected::dispatch($inventory->refresh());

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === NotifyAdminLowStock::class);
    }

    public function test_authenticated_user_can_read_and_mark_in_app_notifications(): void
    {
        $customer = $this->userWithRole(RoleEnum::Customer);
        $customer->notify(new WelcomeNotification);
        Sanctum::actingAs($customer);

        $notificationId = $customer->notifications()->firstOrFail()->id;

        $this->getJson('/api/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1);

        $this->patchJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notificationId);

        $this->getJson('/api/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.unread_count', 0);
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

        return Order::query()->findOrFail($orderId);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $roleModel = $this->createRole($role);
        $user = User::factory()->create();
        $user->roles()->attach($roleModel);

        return $user;
    }

    private function createRole(RoleEnum $role): Role
    {
        return Role::query()->firstOrCreate(
            ['name' => $role->value],
            ['label' => $role->label()],
        );
    }
}
