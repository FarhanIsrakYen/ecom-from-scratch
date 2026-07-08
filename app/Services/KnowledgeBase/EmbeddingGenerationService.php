<?php

namespace App\Services\KnowledgeBase;

use App\Models\DocumentChunk;
use App\Services\AI\AIProviderInterface;

class EmbeddingGenerationService
{
    public function __construct(private readonly AIProviderInterface $provider) {}

    /**
     * @return array<int, float>
     */
    public function generateForText(string $text): array
    {
        return $this->provider->generateEmbedding($text);
    }

    public function generateForChunk(DocumentChunk $chunk): void
    {
        $vector = $this->generateForText($chunk->content);

        $chunk->embedding()->updateOrCreate(
            ['document_chunk_id' => $chunk->id],
            [
                'provider' => (string) config('services.ai.provider', 'openai'),
                'model' => (string) config('services.openai.embedding_model', 'text-embedding-3-small'),
                'vector' => $vector,
                'dimensions' => count($vector),
                'content_hash' => hash('sha256', $chunk->content),
            ],
        );
    }
}
