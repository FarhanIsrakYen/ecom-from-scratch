<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['faq', 'refund_policy', 'delivery_policy', 'platform_policy']),
            'source_type' => null,
            'source_id' => null,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
            'metadata' => [],
            'status' => 'active',
        ];
    }
}
