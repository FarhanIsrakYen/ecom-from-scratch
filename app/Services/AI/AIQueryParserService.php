<?php

namespace App\Services\AI;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class AIQueryParserService
{
    public function __construct(private readonly AIProviderInterface $provider) {}

    public function parse(string $query): array
    {
        $query = Str::of($query)->squish()->limit(300, '')->toString();

        try {
            $parsed = $this->provider->parseProductSearchQuery($query);
        } catch (Throwable) {
            $parsed = $this->fallbackParse($query);
        }

        $validated = $this->validateParsedFilters($parsed);

        if (($validated['relevant'] ?? false) !== true) {
            return [
                'relevant' => false,
                'filters' => [],
                'search_filters' => [],
                'message' => 'I can help with product searches. Try asking for a product, category, color, size, price range, or brand.',
            ];
        }

        $filters = $this->sanitizeFilters($validated);

        if ($filters === []) {
            $filters = $this->sanitizeFilters($this->fallbackParse($query));
        }

        if ($filters === []) {
            return [
                'relevant' => false,
                'filters' => [],
                'search_filters' => [],
                'message' => 'I could not find product search filters in that request. Try adding a product type, category, color, size, price, or brand.',
            ];
        }

        return [
            'relevant' => true,
            'filters' => $filters,
            'search_filters' => $this->toSearchFilters($filters),
            'message' => 'Product search filters extracted.',
        ];
    }

    private function validateParsedFilters(array $parsed): array
    {
        $validator = Validator::make($parsed, [
            'relevant' => ['sometimes', 'boolean'],
            'product_type' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN\s&.,_-]+$/u'],
            'category' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN\s&.,_-]+$/u'],
            'color' => ['nullable', 'string', 'max:40', 'regex:/^[\pL\pN\s_-]+$/u'],
            'size' => ['nullable', 'string', 'max:20', 'regex:/^[\pL\pN\s._-]+$/u'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'brand' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\pN\s&.,_-]+$/u'],
        ]);

        if ($validator->fails()) {
            return $this->fallbackParse('');
        }

        return $validator->validated();
    }

    private function sanitizeFilters(array $filters): array
    {
        $sanitized = [];

        foreach (['product_type', 'category', 'color', 'size', 'brand'] as $field) {
            $value = $this->cleanText((string) ($filters[$field] ?? ''));

            if ($value !== '') {
                $sanitized[$field] = $value;
            }
        }

        foreach (['price_min', 'price_max'] as $field) {
            if (array_key_exists($field, $filters) && is_numeric($filters[$field])) {
                $sanitized[$field] = max(0, (float) $filters[$field]);
            }
        }

        if (isset($sanitized['price_min'], $sanitized['price_max']) && $sanitized['price_min'] > $sanitized['price_max']) {
            [$sanitized['price_min'], $sanitized['price_max']] = [$sanitized['price_max'], $sanitized['price_min']];
        }

        return $sanitized;
    }

    private function toSearchFilters(array $filters): array
    {
        $search = ['sort' => 'relevance'];

        if (isset($filters['product_type'])) {
            $search['q'] = $filters['product_type'];
        }

        if (isset($filters['category'])) {
            $category = $this->resolveCategory($filters['category']);
            $search[$category !== null ? 'category' : 'q'] = $category ?? trim(($search['q'] ?? '').' '.$filters['category']);
        }

        if (isset($filters['brand'])) {
            $brand = $this->resolveBrand($filters['brand']);

            if ($brand !== null) {
                $search['brand'] = $brand;
            } else {
                $search['q'] = trim(($search['q'] ?? '').' '.$filters['brand']);
            }
        }

        if (isset($filters['color'])) {
            $search['attributes']['color'] = Str::lower($filters['color']);
        }

        if (isset($filters['size'])) {
            $search['attributes']['size'] = Str::upper($filters['size']);
        }

        if (isset($filters['price_min'])) {
            $search['min_price'] = $filters['price_min'];
        }

        if (isset($filters['price_max'])) {
            $search['max_price'] = $filters['price_max'];
        }

        return $search;
    }

    private function fallbackParse(string $query): array
    {
        $lower = Str::lower($query);
        $filters = [
            'relevant' => false,
            'product_type' => null,
            'category' => null,
            'color' => null,
            'size' => null,
            'price_min' => null,
            'price_max' => null,
            'brand' => null,
        ];

        if (preg_match('/\b(t-?shirts?|shirts?|shoes?|sneakers?|pants?|jeans?|dresses?|bags?|backpacks?|cameras?|phones?)\b/i', $query, $matches)) {
            $filters['relevant'] = true;
            $filters['product_type'] = $matches[1];
        }

        if (preg_match('/\b(black|white|red|blue|green|yellow|pink|purple|gray|grey|brown|orange)\b/i', $query, $matches)) {
            $filters['relevant'] = true;
            $filters['color'] = $matches[1];
        }

        if (preg_match('/\b(xs|s|m|l|xl|xxl|xxxl|\d{1,2})\b/i', $query, $matches)) {
            $filters['relevant'] = true;
            $filters['size'] = $matches[1];
        }

        if (preg_match('/\b(?:under|below|less than|max|maximum)\s+(?:bdt|tk|taka)?\s*(\d+(?:\.\d+)?)/i', $query, $matches)) {
            $filters['relevant'] = true;
            $filters['price_max'] = (float) $matches[1];
        }

        if (preg_match('/\b(?:over|above|more than|min|minimum)\s+(?:bdt|tk|taka)?\s*(\d+(?:\.\d+)?)/i', $query, $matches)) {
            $filters['relevant'] = true;
            $filters['price_min'] = (float) $matches[1];
        }

        if (! $filters['relevant'] && str_contains($lower, 'product')) {
            $filters['relevant'] = true;
            $filters['product_type'] = 'product';
        }

        return $filters;
    }

    private function resolveCategory(string $category): ?string
    {
        $slug = Str::slug($category);

        return Category::query()
            ->where('slug', $slug)
            ->orWhere('name', $category)
            ->value('slug');
    }

    private function resolveBrand(string $brand): ?string
    {
        $slug = Str::slug($brand);

        return Brand::query()
            ->where('slug', $slug)
            ->orWhere('name', $brand)
            ->value('slug');
    }

    private function cleanText(string $value): string
    {
        return Str::of($value)
            ->squish()
            ->replaceMatches('/[^\pL\pN\s&.,_-]/u', '')
            ->limit(80, '')
            ->toString();
    }
}
