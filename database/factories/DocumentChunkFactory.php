<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    protected $model = DocumentChunk::class;

    public function definition(): array
    {
        $content = fake()->paragraph();

        return [
            'document_id' => Document::factory(),
            'chunk_index' => 0,
            'content' => $content,
            'metadata' => ['content_hash' => hash('sha256', $content)],
        ];
    }
}
