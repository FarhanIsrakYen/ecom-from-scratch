<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Events\OrderPaid;
use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Stripe\Checkout\Session;
use Tests\TestCase;

class StripePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_stripe_checkout_session_for_order(): void
    {
        $this->fakeStripeGateway();
        $user = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createCheckoutOrder($user, 2, 40);

        $this->withToken($user->createToken('phpunit')->plainTextToken)
            ->postJson('/api/payments/stripe/checkout-sessions', ['order_id' => $order->id])
            ->assertCreated()
            ->assertJsonPath('data.checkout_session_id', 'cs_test_123')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/session')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.amount', '80.00');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_checkout_session_id' => 'cs_test_123',
            'provider_payment_id' => 'pi_test_123',
            'status' => 'pending',
        ]);
    }

    public function test_stripe_payment_success_marks_order_paid_and_converts_reserved_stock_to_sold(): void
    {
        Event::fake([OrderPaid::class]);
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $user = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createCheckoutOrder($user, 2, 25);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_success',
            'amount' => $order->total,
            'currency' => 'usd',
            'status' => 'pending',
        ]);

        $payload = $this->stripeEventPayload('evt_success', 'payment_intent.succeeded', [
            'object' => 'payment_intent',
            'id' => 'pi_success',
            'metadata' => [
                'order_id' => (string) $order->id,
                'payment_id' => (string) $payment->id,
            ],
        ]);

        $this->postSignedStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.processed', true);

        $this->postSignedStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.processed', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $order->items()->first()->product_variant_id,
            'reserved_stock' => 0,
            'sold_stock' => 2,
        ]);
        $this->assertDatabaseCount('processed_webhook_events', 1);
        Event::assertDispatched(OrderPaid::class);
    }

    public function test_stripe_payment_failure_marks_payment_failed_and_releases_reserved_stock(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $user = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createCheckoutOrder($user, 3, 20);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_failed',
            'amount' => $order->total,
            'currency' => 'usd',
            'status' => 'pending',
        ]);

        $payload = $this->stripeEventPayload('evt_failed', 'payment_intent.payment_failed', [
            'object' => 'payment_intent',
            'id' => 'pi_failed',
            'metadata' => [
                'payment_id' => (string) $payment->id,
            ],
        ]);

        $this->postSignedStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.processed', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'payment_status' => 'failed',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $order->items()->first()->product_variant_id,
            'available_stock' => 5,
            'reserved_stock' => 0,
        ]);
    }

    public function test_stripe_refund_marks_order_and_payment_refunded(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $user = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createCheckoutOrder($user, 1, 50);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_refund',
            'amount' => $order->total,
            'currency' => 'usd',
            'status' => 'paid',
        ]);
        $order->update(['status' => 'paid', 'payment_status' => 'paid']);

        Event::fake([OrderRefunded::class]);

        $payload = $this->stripeEventPayload('evt_refund', 'charge.refunded', [
            'object' => 'charge',
            'id' => 'ch_refund',
            'payment_intent' => 'pi_refund',
        ]);

        $this->postSignedStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.processed', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'refunded',
            'payment_status' => 'refunded',
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'refunded',
        ]);
        Event::assertDispatched(OrderRefunded::class);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 'invalid', 'CONTENT_TYPE' => 'application/json'],
            $this->stripeEventPayload('evt_bad', 'payment_intent.succeeded', []),
        )->assertStatus(400);
    }

    private function createCheckoutOrder(User $user, int $quantity, float $unitPrice): Order
    {
        $variant = ProductVariant::factory()->create(['price' => $unitPrice, 'stock' => 0]);
        app(InventoryService::class)->increaseStock($variant->product_id, $variant->id, 5, 'Initial stock');
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/cart/items', [
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ])->assertCreated();

        $orderId = $this->withToken($token)
            ->postJson('/api/checkout', [
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

    private function postSignedStripeWebhook(string $payload)
    {
        return $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $this->stripeSignatureHeader($payload, 'whsec_test'),
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        );
    }

    private function stripeSignatureHeader(string $payload, string $secret): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return 't='.$timestamp.',v1='.$signature;
    }

    private function stripeEventPayload(string $id, string $type, array $object): string
    {
        return json_encode([
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => $object,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function fakeStripeGateway(): void
    {
        $this->app->bind(StripePaymentGateway::class, fn () => new class extends StripePaymentGateway
        {
            public function createCheckoutSession(Order $order, Payment $payment): Session
            {
                return Session::constructFrom([
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                    'url' => 'https://checkout.stripe.test/session',
                    'payment_intent' => 'pi_test_123',
                ]);
            }
        });
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
