<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\BuildsCursorPaginationMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    use ApiResponse;
    use BuildsCursorPaginationMeta;

    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $paginator = $this->products->adminPaginate($request->validate([
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

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        return $this->success(
            new ProductResource($this->products->create($request->validated())),
            'Product created.',
            201,
        );
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('view', $product);

        return $this->success(
            new ProductResource($product->load(['category', 'brand', 'variants', 'images'])),
            'Product retrieved.',
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        return $this->success(
            new ProductResource($this->products->update($product, $request->validated())),
            'Product updated.',
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);
        $this->products->delete($product);

        return $this->success(null, 'Product deleted.');
    }
}
