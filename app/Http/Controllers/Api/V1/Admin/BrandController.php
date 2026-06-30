<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\BuildsCursorPaginationMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\BrandService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BrandController extends Controller
{
    use ApiResponse;
    use BuildsCursorPaginationMeta;

    public function __construct(private readonly BrandService $brands) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->brands->paginate($request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ]));

        return $this->success(BrandResource::collection($paginator->items()), 'Brands retrieved.', 200, [
            ...$this->cursorPaginationMeta($paginator),
        ]);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        Gate::authorize('create', Brand::class);

        return $this->success(
            new BrandResource($this->brands->create($request->validated())),
            'Brand created.',
            201,
        );
    }

    public function show(Brand $brand): JsonResponse
    {
        Gate::authorize('view', $brand);

        return $this->success(
            new BrandResource($brand),
            'Brand retrieved.',
        );
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        Gate::authorize('update', $brand);

        return $this->success(
            new BrandResource($this->brands->update($brand, $request->validated())),
            'Brand updated.',
        );
    }

    public function destroy(Brand $brand): JsonResponse
    {
        Gate::authorize('delete', $brand);
        $this->brands->delete($brand);

        return $this->success(null, 'Brand deleted.');
    }
}
