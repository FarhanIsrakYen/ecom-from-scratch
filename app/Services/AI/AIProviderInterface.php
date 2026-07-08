<?php

namespace App\Services\AI;

interface AIProviderInterface
{
    /**
     * @return array{
     *     relevant?: bool,
     *     product_type?: string|null,
     *     category?: string|null,
     *     color?: string|null,
     *     size?: string|null,
     *     price_min?: int|float|null,
     *     price_max?: int|float|null,
     *     brand?: string|null
     * }
     */
    public function parseProductSearchQuery(string $query): array;

    /**
     * @return array<int, float>
     */
    public function generateEmbedding(string $text): array;

    /**
     * @param  array<int, array<string, mixed>>  $contexts
     * @return array{relevant?: bool, answer?: string}
     */
    public function answerShoppingAssistant(string $question, array $contexts): array;
}
