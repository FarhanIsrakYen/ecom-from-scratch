<?php

namespace Tests\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleEnum;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\AI\AIProviderInterface;
use App\Services\InventoryService;
use App\Services\StripePaymentGateway;
use Illuminate\Support\Facades\Queue;
use Stripe\Checkout\Session;

trait InteractsWithEcommerce
{
    protected function userWithRole(RoleEnum $role, array $attributes = []): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role->value],
            ['label' => $role->label()],
        );

        $user = User::factory()->create($attributes);
        $user->roles()->attach($roleModel);

        return $user;
    }

    protected function actingAsRole(RoleEnum $role, array $attributes = []): User
    {
        $user = $this->userWithRole($role, $attributes);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    protected function createCheckoutOrder(User $user, int $quantity = 1, float $unitPrice = 25): Order
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

    protected function createAwaitingPaymentOrder(User $user, float $total = 50): Order
    {
        return Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::AwaitingPayment->value,
            'payment_status' => PaymentStatus::Pending->value,
            'subtotal' => $total,
            'discount' => 0,
            'delivery_charge' => 0,
            'tax' => 0,
            'total' => $total,
        ]);
    }

    protected function fakeStripeGateway(): void
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

    protected function fakeAiProvider(array $parseResponse = [], array $assistantResponse = []): void
    {
        $this->app->bind(AIProviderInterface::class, fn () => new class($parseResponse, $assistantResponse) implements AIProviderInterface
        {
            public function __construct(
                private readonly array $parseResponse,
                private readonly array $assistantResponse,
            ) {}

            public function parseProductSearchQuery(string $query): array
            {
                return $this->parseResponse;
            }

            public function generateEmbedding(string $text): array
            {
                return [1.0, 0.0, 0.5];
            }

            public function answerShoppingAssistant(string $question, array $contexts): array
            {
                return $this->assistantResponse !== []
                    ? $this->assistantResponse
                    : ['relevant' => false, 'answer' => 'No matching context.'];
            }
        });
    }

    protected function fakeRabbitMqBroker(): void
    {
        config(['queue.default' => 'sync']);
        Queue::fake();
    }
}
