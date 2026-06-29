<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        return $this->success([
            'status' => 'ok',
            'service' => config('app.name'),
            'environment' => app()->environment(),
        ], 'Service healthy.');
    }
}
