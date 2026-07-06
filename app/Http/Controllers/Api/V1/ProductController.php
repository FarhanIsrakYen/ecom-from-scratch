<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\BuildsCursorPaginationMeta;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\ProductCacheService;
use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;
    use BuildsCursorPaginationMeta;

    public function __construct(
        private readonly ProductService $products,
        private readonly ProductCacheService $cache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'string'],
            'brand' => ['sometimes', 'string'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'in:active,inactive,draft'],
            'featured' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', 'in:latest,price_low_to_high,price_high_to_low'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ]);

        $callback = function () use ($filters, $request): array {
            $paginator = $this->products->paginate($filters);

            return [
                'data' => ProductResource::collection($paginator->items())->resolve($request),
                'meta' => $this->cursorPaginationMeta($paginator),
            ];
        };

        $payload = array_key_exists('featured', $filters) && filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN)
            ? $this->cache->rememberFeatured($filters, $callback)
            : $this->cache->rememberListing($filters, $callback);

        return $this->success(
            $payload['data'],
            'Products retrieved.',
            200,
            $payload['meta'],
        );
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->cache->rememberDetail(
            $slug,
            fn (): array => (new ProductResource($this->products->findBySlug($slug)))->resolve(request()),
        );

        return $this->success(
            $product,
            'Product retrieved.',
        );
    }
}
