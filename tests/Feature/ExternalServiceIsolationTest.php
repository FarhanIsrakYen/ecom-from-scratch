<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Jobs\GenerateDocumentChunkEmbedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InteractsWithEcommerce;
use Tests\TestCase;

class ExternalServiceIsolationTest extends TestCase
{
    use InteractsWithEcommerce;
    use RefreshDatabase;

    public function test_stripe_checkout_uses_fake_gateway(): void
    {
        $this->fakeStripeGateway();
        $user = $this->userWithRole(RoleEnum::Customer);
        $order = $this->createAwaitingPaymentOrder($user, 75);

        $this
            ->withToken($user->createToken('phpunit')->plainTextToken)
            ->postJson('/api/payments/stripe/checkout-sessions', ['order_id' => $order->id])
            ->assertCreated()
            ->assertJsonPath('data.checkout_session_id', 'cs_test_123')
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/session');
    }

    public function test_ai_features_use_fake_provider(): void
    {
        $this->fakeAiProvider([
            'relevant' => false,
            'product_type' => null,
            'category' => null,
            'color' => null,
            'size' => null,
            'price_min' => null,
            'price_max' => null,
            'brand' => null,
        ]);

        $this
            ->postJson('/api/ai/product-search', ['query' => 'show me something unrelated'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_embedding_jobs_are_queued_without_reaching_a_real_broker(): void
    {
        $this->fakeRabbitMqBroker();
        $this->actingAsRole(RoleEnum::Admin);

        $this
            ->postJson('/api/admin/knowledge-base/sync', [
                'documents' => [
                    [
                        'type' => 'faq',
                        'title' => 'Returns',
                        'content' => 'Returns are available within seven days.',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.synced_documents', 1);

        Queue::assertPushed(GenerateDocumentChunkEmbedding::class);
    }
}
