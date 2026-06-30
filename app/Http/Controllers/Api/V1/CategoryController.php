<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CategoryService $categories) {}

    public function index(): JsonResponse
    {
        return $this->success(
            CategoryResource::collection($this->categories->publicList()),
            'Categories retrieved.',
        );
    }
}
