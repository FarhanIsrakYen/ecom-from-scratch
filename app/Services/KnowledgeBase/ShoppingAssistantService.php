<?php

namespace App\Services\KnowledgeBase;

use App\Services\AI\AIProviderInterface;
use Throwable;

class ShoppingAssistantService
{
    public function __construct(
        private readonly KnowledgeRetrievalService $retrieval,
        private readonly AIProviderInterface $provider,
    ) {}

    /**
     * @return array{relevant: bool, answer: string, sources: array<int, array<string, mixed>>, context: array<int, array<string, mixed>>}
     */
    public function answer(string $question): array
    {
        $contexts = $this->retrieval->retrieve($question);

        if ($contexts === []) {
            return [
                'relevant' => false,
                'answer' => 'I can only answer using available product and platform information, and I could not find relevant data for that question.',
                'sources' => [],
                'context' => [],
            ];
        }

        try {
            $response = $this->provider->answerShoppingAssistant($question, $contexts);
        } catch (Throwable) {
            $response = [
                'relevant' => true,
                'answer' => 'Based on the available data: '.$contexts[0]['content'],
            ];
        }

        $relevant = ($response['relevant'] ?? false) === true;

        return [
            'relevant' => $relevant,
            'answer' => $relevant
                ? (string) ($response['answer'] ?? 'The available data does not include enough detail to answer that.')
                : 'I can only answer using available product and platform information, and the retrieved data was not enough for that question.',
            'sources' => $this->sources($contexts),
            'context' => $contexts,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $contexts
     * @return array<int, array<string, mixed>>
     */
    private function sources(array $contexts): array
    {
        return collect($contexts)
            ->map(fn (array $context): array => [
                'document_id' => $context['document_id'],
                'document_type' => $context['document_type'],
                'document_title' => $context['document_title'],
                'chunk_id' => $context['chunk_id'],
                'source_type' => $context['source_type'],
                'source_id' => $context['source_id'],
                'product_id' => $context['product_id'],
                'score' => $context['score'],
            ])
            ->unique(fn (array $source): string => $source['document_id'].'-'.$source['chunk_id'])
            ->values()
            ->all();
    }
}
