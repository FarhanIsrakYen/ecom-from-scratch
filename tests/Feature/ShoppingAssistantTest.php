<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Document;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\KnowledgeBase\KnowledgeBaseSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShoppingAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai.provider' => 'mock']);
    }

    public function test_assistant_answers_from_product_context_and_returns_sources(): void
    {
        config([
            'services.ai.mock_assistant_response' => [
                'relevant' => true,
                'answer' => 'The CodeMaster 14 is suitable for programming and costs 75000 taka.',
            ],
        ]);

        $product = Product::factory()->create([
            'name' => 'CodeMaster 14 Laptop',
            'description' => 'A programming laptop with 16GB RAM, SSD storage, and a quiet keyboard.',
            'base_price' => 75000,
        ]);

        app(KnowledgeBaseSyncService::class)->syncProduct($product);

        $this->postJson('/api/ai/assistant', [
            'question' => 'Which laptop is best for programming under 80000 taka?',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.relevant', true)
            ->assertJsonPath('data.answer', 'The CodeMaster 14 is suitable for programming and costs 75000 taka.')
            ->assertJsonPath('data.sources.0.document_type', 'product')
            ->assertJsonPath('data.sources.0.product_id', $product->id);
    }

    public function test_assistant_can_answer_platform_policy_questions(): void
    {
        config([
            'services.ai.mock_assistant_response' => [
                'relevant' => true,
                'answer' => 'Refunds are available within 7 days for unused products.',
            ],
        ]);

        app(KnowledgeBaseSyncService::class)->syncPlatformDocument(
            'refund_policy',
            'Refund Policy',
            'Customers may request a refund within 7 days if the product is unused and returned with packaging.',
        );

        $this->postJson('/api/ai/assistant', [
            'question' => 'What is the refund policy?',
        ])
            ->assertOk()
            ->assertJsonPath('data.relevant', true)
            ->assertJsonPath('data.sources.0.document_type', 'refund_policy')
            ->assertJsonPath('data.sources.0.document_title', 'Refund Policy');
    }

    public function test_irrelevant_question_is_not_answered_without_context(): void
    {
        $this->postJson('/api/ai/assistant', [
            'question' => 'Write a movie script about space travel',
        ])
            ->assertOk()
            ->assertJsonPath('data.relevant', false)
            ->assertJsonPath('data.sources', [])
            ->assertJsonPath('message', 'Question could not be answered from available data.');
    }

    public function test_admin_can_sync_products_and_platform_documents(): void
    {
        $admin = $this->userWithRole(RoleEnum::Admin);
        $product = Product::factory()->create(['name' => 'GamePhone X']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/knowledge-base/sync', [
            'sync_products' => true,
            'product_ids' => [$product->id],
            'documents' => [
                [
                    'type' => 'delivery_policy',
                    'title' => 'Delivery Policy',
                    'content' => 'Inside Dhaka delivery usually takes 2 days.',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.synced_products', 1)
            ->assertJsonPath('data.synced_documents', 1);

        $this->assertDatabaseHas('documents', [
            'type' => 'product',
            'source_id' => $product->id,
        ]);
        $this->assertDatabaseHas('documents', [
            'type' => 'delivery_policy',
            'title' => 'Delivery Policy',
        ]);
        $this->assertDatabaseCount('embeddings', 2);
    }

    public function test_admin_can_sync_all_product_data_into_knowledge_base(): void
    {
        $admin = $this->userWithRole(RoleEnum::Admin);
        Product::factory()->count(2)->create(['status' => 'active']);
        Product::factory()->create(['status' => 'draft']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/knowledge-base/sync', [
            'sync_products' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.synced_products', 2);

        $this->assertSame(2, Document::query()->where('type', 'product')->count());
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
