<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\BuildsCursorPaginationMeta;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\AI\AIQueryParserService;
use App\Services\ProductSearchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIProductSearchController extends Controller
{
    use ApiResponse;
    use BuildsCursorPaginationMeta;

    public function __construct(
        private readonly AIQueryParserService $parser,
        private readonly ProductSearchService $search,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:300'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ]);

        $parsed = $this->parser->parse($data['query']);

        if (($parsed['relevant'] ?? false) !== true) {
            return $this->success([], $parsed['message'], 200, [
                'extracted_filters' => $parsed['filters'],
                'search_filters' => $parsed['search_filters'],
            ]);
        }

        $searchFilters = [
            ...$parsed['search_filters'],
            ...array_intersect_key($data, ['per_page' => true, 'cursor' => true]),
        ];

        $result = $this->search->search($searchFilters, $request->user('sanctum'));
        $paginator = $result['paginator'];

        return $this->success(
            ProductResource::collection($paginator->items())->resolve($request),
            'Products retrieved from your natural language search.',
            200,
            [
                ...$this->cursorPaginationMeta($paginator),
                'extracted_filters' => $parsed['filters'],
                'search_filters' => $searchFilters,
                'facets' => $result['facets'],
                'suggestions' => $result['suggestions'],
                'popular_searches' => $result['popular_searches'],
            ],
        );
    }
}
