<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\BuildsCursorPaginationMeta;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;
    use BuildsCursorPaginationMeta;

    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->products->paginate($request->validate([
            'category' => ['sometimes', 'string'],
            'brand' => ['sometimes', 'string'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', 'in:active,inactive,draft'],
            'featured' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', 'in:latest,price_low_to_high,price_high_to_low'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ]));

        return $this->success(
            ProductResource::collection($paginator->items()),
            'Products retrieved.',
            200,
            $this->cursorPaginationMeta($paginator),
        );
    }

    public function show(string $slug): JsonResponse
    {
        return $this->success(
            new ProductResource($this->products->findBySlug($slug)),
            'Product retrieved.',
        );
    }
}
