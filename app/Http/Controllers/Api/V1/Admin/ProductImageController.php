<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\BuildsCursorPaginationMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductImageRequest;
use App\Http\Requests\Catalog\UpdateProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductImageController extends Controller
{
    use ApiResponse;
    use BuildsCursorPaginationMeta;

    public function __construct(private readonly ProductImageService $images) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductImage::class);

        $paginator = $this->images->paginate($request->validate([
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ]));

        return $this->success(
            ProductImageResource::collection($paginator->items()),
            'Product images retrieved.',
            200,
            $this->cursorPaginationMeta($paginator),
        );
    }

    public function store(StoreProductImageRequest $request): JsonResponse
    {
        Gate::authorize('create', ProductImage::class);

        return $this->success(
            new ProductImageResource($this->images->create($request->validated())),
            'Product image created.',
            201,
        );
    }

    public function show(ProductImage $image): JsonResponse
    {
        Gate::authorize('view', $image);

        return $this->success(
            new ProductImageResource($image),
            'Product image retrieved.',
        );
    }

    public function update(UpdateProductImageRequest $request, ProductImage $image): JsonResponse
    {
        Gate::authorize('update', $image);

        return $this->success(
            new ProductImageResource($this->images->update($image, $request->validated())),
            'Product image updated.',
        );
    }

    public function destroy(ProductImage $image): JsonResponse
    {
        Gate::authorize('delete', $image);
        $this->images->delete($image);

        return $this->success(null, 'Product image deleted.');
    }
}
