<?php

namespace App\Services\KnowledgeBase;

use App\Models\DocumentChunk;
use Illuminate\Support\Str;

class KnowledgeRetrievalService
{
    public function __construct(private readonly EmbeddingGenerationService $embeddings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function retrieve(string $question, ?int $limit = null): array
    {
        $limit ??= (int) config('knowledge_base.retrieval_limit', 5);
        $minimumScore = (float) config('knowledge_base.minimum_score', 0.08);
        $queryVector = $this->embeddings->generateForText($question);
        $queryTokens = $this->tokens($question);

        return DocumentChunk::query()
            ->with(['document', 'embedding'])
            ->whereHas('document', fn ($query) => $query->where('status', 'active'))
            ->whereHas('embedding')
            ->get()
            ->map(function (DocumentChunk $chunk) use ($queryVector, $queryTokens): array {
                $vector = $chunk->embedding?->vector ?? [];
                $semantic = $this->cosine($queryVector, $vector);
                $keyword = $this->keywordOverlap($queryTokens, $this->tokens($chunk->content));
                $score = ($semantic * 0.75) + ($keyword * 0.25);

                return [
                    'chunk' => $chunk,
                    'score' => $score,
                    'semantic_score' => $semantic,
                    'keyword_score' => $keyword,
                ];
            })
            ->filter(fn (array $result): bool => $result['score'] >= $minimumScore || $result['keyword_score'] >= 0.12)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(fn (array $result): array => $this->formatResult($result))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function formatResult(array $result): array
    {
        /** @var DocumentChunk $chunk */
        $chunk = $result['chunk'];
        $document = $chunk->document;

        return [
            'document_id' => $document->id,
            'document_type' => $document->type,
            'document_title' => $document->title,
            'chunk_id' => $chunk->id,
            'chunk_index' => $chunk->chunk_index,
            'source_type' => $document->source_type,
            'source_id' => $document->source_id,
            'product_id' => $document->type === 'product' ? $document->source_id : data_get($document->metadata, 'product_id'),
            'content' => $chunk->content,
            'score' => round($result['score'], 6),
        ];
    }

    /**
     * @param  array<int, float>  $left
     * @param  array<int, float>  $right
     */
    private function cosine(array $left, array $right): float
    {
        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;
        $dimensions = min(count($left), count($right));

        for ($i = 0; $i < $dimensions; $i++) {
            $a = (float) $left[$i];
            $b = (float) $right[$i];
            $dot += $a * $b;
            $leftNorm += $a * $a;
            $rightNorm += $b * $b;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $text): array
    {
        $tokens = str_word_count(Str::lower($text), 1, '0123456789');

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token): bool => strlen($token) > 2 && ! in_array($token, ['the', 'and', 'for', 'with', 'this', 'that', 'from'], true),
        )));
    }

    /**
     * @param  array<int, string>  $queryTokens
     * @param  array<int, string>  $chunkTokens
     */
    private function keywordOverlap(array $queryTokens, array $chunkTokens): float
    {
        if ($queryTokens === []) {
            return 0.0;
        }

        return count(array_intersect($queryTokens, $chunkTokens)) / count($queryTokens);
    }
}
