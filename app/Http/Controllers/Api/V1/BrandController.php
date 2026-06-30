<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly BrandService $brands) {}

    public function index(): JsonResponse
    {
        return $this->success(
            BrandResource::collection($this->brands->publicList()),
            'Brands retrieved.',
        );
    }
}
