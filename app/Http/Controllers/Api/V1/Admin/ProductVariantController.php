<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\BuildsCursorPaginationMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductVariantRequest;
use App\Http\Requests\Catalog\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\ProductVariant;
use App\Services\ProductVariantService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductVariantController extends Controller
{
    use ApiResponse;
    use BuildsCursorPaginationMeta;

    public function __construct(private readonly ProductVariantService $variants) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductVariant::class);

        $paginator = $this->variants->paginate($request->validate([
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ]));

        return $this->success(
            ProductVariantResource::collection($paginator->items()),
            'Product variants retrieved.',
            200,
            $this->cursorPaginationMeta($paginator),
        );
    }

    public function store(StoreProductVariantRequest $request): JsonResponse
    {
        Gate::authorize('create', ProductVariant::class);

        return $this->success(
            new ProductVariantResource($this->variants->create($request->validated())),
            'Product variant created.',
            201,
        );
    }

    public function show(ProductVariant $variant): JsonResponse
    {
        Gate::authorize('view', $variant);

        return $this->success(
            new ProductVariantResource($variant),
            'Product variant retrieved.',
        );
    }

    public function update(UpdateProductVariantRequest $request, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('update', $variant);

        return $this->success(
            new ProductVariantResource($this->variants->update($variant, $request->validated())),
            'Product variant updated.',
        );
    }

    public function destroy(ProductVariant $variant): JsonResponse
    {
        Gate::authorize('delete', $variant);
        $this->variants->delete($variant);

        return $this->success(null, 'Product variant deleted.');
    }
}
