<?php

namespace App\Jobs;

use App\Models\DocumentChunk;
use App\Services\KnowledgeBase\EmbeddingGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDocumentChunkEmbedding implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $documentChunkId) {}

    public function handle(EmbeddingGenerationService $embeddings): void
    {
        $chunk = DocumentChunk::query()->find($this->documentChunkId);

        if ($chunk === null) {
            return;
        }

        $embeddings->generateForChunk($chunk);
    }
}
