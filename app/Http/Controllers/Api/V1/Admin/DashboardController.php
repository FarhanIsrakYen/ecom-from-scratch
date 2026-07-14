<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __invoke(DashboardService $dashboard): JsonResponse
    {
        return $this->success($dashboard->data(), 'Dashboard retrieved.');
    }
}
