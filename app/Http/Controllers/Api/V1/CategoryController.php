<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryCacheService;
use App\Services\CategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CategoryService $categories,
        private readonly CategoryCacheService $cache,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            $this->cache->rememberTree(
                fn (): array => CategoryResource::collection($this->categories->publicList())->resolve(request()),
            ),
            'Categories retrieved.',
        );
    }
}
