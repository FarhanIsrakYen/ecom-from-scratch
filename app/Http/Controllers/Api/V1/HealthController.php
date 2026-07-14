<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    use ApiResponse;

    public function __invoke(SystemHealthService $health): JsonResponse
    {
        $report = $health->report();

        return $this->success(
            $report['payload'],
            $report['healthy'] ? 'Service healthy.' : 'Service degraded.',
            $report['healthy'] ? 200 : 503,
        );
    }
}
