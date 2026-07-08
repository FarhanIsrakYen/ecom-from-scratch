<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements AIProviderInterface
{
    public function parseProductSearchQuery(string $query): array
    {
        $apiKey = (string) config('services.openai.key');

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout((int) config('services.openai.timeout', 10))
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-4.1-mini'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'Extract product search filters from customer queries. Return only valid JSON. If the query is not about shopping for products, set relevant to false.',
                        ]],
                    ],
                    [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $query,
                        ]],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'product_search_filters',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'relevant' => ['type' => 'boolean'],
                                'product_type' => ['type' => ['string', 'null']],
                                'category' => ['type' => ['string', 'null']],
                                'color' => ['type' => ['string', 'null']],
                                'size' => ['type' => ['string', 'null']],
                                'price_min' => ['type' => ['number', 'null']],
                                'price_max' => ['type' => ['number', 'null']],
                                'brand' => ['type' => ['string', 'null']],
                            ],
                            'required' => ['relevant', 'product_type', 'category', 'color', 'size', 'price_min', 'price_max', 'brand'],
                        ],
                    ],
                ],
            ])
            ->throw()
            ->json();

        $text = data_get($response, 'output_text')
            ?? data_get($response, 'output.0.content.0.text')
            ?? data_get($response, 'output.0.content.0.output_text');

        if (! is_string($text)) {
            throw new RuntimeException('OpenAI response did not include parseable text.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI response was not valid JSON.');
        }

        return $decoded;
    }

    public function generateEmbedding(string $text): array
    {
        $apiKey = $this->apiKey();

        $response = Http::withToken($apiKey)
            ->timeout((int) config('services.openai.timeout', 10))
            ->acceptJson()
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => config('services.openai.embedding_model', 'text-embedding-3-small'),
                'input' => $text,
            ])
            ->throw()
            ->json();

        $embedding = data_get($response, 'data.0.embedding');

        if (! is_array($embedding)) {
            throw new RuntimeException('OpenAI embedding response was invalid.');
        }

        return array_map('floatval', $embedding);
    }

    public function answerShoppingAssistant(string $question, array $contexts): array
    {
        $apiKey = $this->apiKey();

        $response = Http::withToken($apiKey)
            ->timeout((int) config('services.openai.timeout', 10))
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-4.1-mini'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'You are a shopping assistant. Answer only from the supplied context. If the context does not answer the question, say that the available data is not enough. Return concise JSON.',
                        ]],
                    ],
                    [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => json_encode([
                                'question' => $question,
                                'context' => $contexts,
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'shopping_assistant_answer',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => [
                                'relevant' => ['type' => 'boolean'],
                                'answer' => ['type' => 'string'],
                            ],
                            'required' => ['relevant', 'answer'],
                        ],
                    ],
                ],
            ])
            ->throw()
            ->json();

        $text = data_get($response, 'output_text')
            ?? data_get($response, 'output.0.content.0.text')
            ?? data_get($response, 'output.0.content.0.output_text');

        if (! is_string($text)) {
            throw new RuntimeException('OpenAI response did not include assistant text.');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI assistant response was not valid JSON.');
        }

        return $decoded;
    }

    private function apiKey(): string
    {
        $apiKey = (string) config('services.openai.key');

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        return $apiKey;
    }
}
