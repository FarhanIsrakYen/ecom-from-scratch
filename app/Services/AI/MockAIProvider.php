<?php

namespace App\Services\AI;

use Illuminate\Support\Str;
use RuntimeException;

class MockAIProvider implements AIProviderInterface
{
    public function __construct(private readonly ?array $response = null) {}

    public function parseProductSearchQuery(string $query): array
    {
        if (config('services.ai.mock_throw') === true) {
            throw new RuntimeException('Mock AI failure.');
        }

        if ($this->response !== null) {
            return $this->response;
        }

        $configured = config('services.ai.mock_response');

        if (is_array($configured)) {
            return $configured;
        }

        $query = Str::lower($query);

        return [
            'relevant' => str_contains($query, 'shirt') || str_contains($query, 'shoe') || str_contains($query, 'product'),
            'product_type' => str_contains($query, 't-shirt') || str_contains($query, 'shirt') ? 't-shirt' : null,
            'category' => null,
            'color' => str_contains($query, 'black') ? 'black' : null,
            'size' => str_contains($query, 'xl') ? 'XL' : null,
            'price_min' => null,
            'price_max' => preg_match('/under\s+(\d+)/', $query, $matches) ? (int) $matches[1] : null,
            'brand' => null,
        ];
    }

    public function generateEmbedding(string $text): array
    {
        if (config('services.ai.mock_embedding_throw') === true) {
            throw new RuntimeException('Mock embedding failure.');
        }

        $tokens = str_word_count(Str::lower($text), 1, '0123456789');
        $vector = array_fill(0, 32, 0.0);

        foreach ($tokens as $token) {
            $vector[crc32($token) % 32] += 1.0;
        }

        return $vector;
    }

    public function answerShoppingAssistant(string $question, array $contexts): array
    {
        $configured = config('services.ai.mock_assistant_response');

        if (is_array($configured)) {
            return $configured;
        }

        if ($contexts === []) {
            return [
                'relevant' => false,
                'answer' => 'I can only answer using available product and platform information, and I could not find relevant data for that question.',
            ];
        }

        return [
            'relevant' => true,
            'answer' => 'Based on the available data: '.$contexts[0]['content'],
        ];
    }
}
