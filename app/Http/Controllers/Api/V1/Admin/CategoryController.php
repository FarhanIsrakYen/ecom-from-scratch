<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\BuildsCursorPaginationMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    use ApiResponse;
    use BuildsCursorPaginationMeta;

    public function __construct(private readonly CategoryService $categories) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->categories->paginate($request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string'],
        ]));

        return $this->success(CategoryResource::collection($paginator->items()), 'Categories retrieved.', 200, [
            ...$this->cursorPaginationMeta($paginator),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        return $this->success(
            new CategoryResource($this->categories->create($request->validated())),
            'Category created.',
            201,
        );
    }

    public function show(Category $category): JsonResponse
    {
        Gate::authorize('view', $category);

        return $this->success(
            new CategoryResource($category->load('parent', 'children')),
            'Category retrieved.',
        );
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        Gate::authorize('update', $category);

        return $this->success(
            new CategoryResource($this->categories->update($category, $request->validated())),
            'Category updated.',
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);
        $this->categories->delete($category);

        return $this->success(null, 'Category deleted.');
    }
}
