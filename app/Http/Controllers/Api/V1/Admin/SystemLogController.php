<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemLogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SystemLogService $logs) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['sometimes', 'string', Rule::in($this->logs->allowedChannels())],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $channel = $data['channel'] ?? 'app';
        $limit = (int) ($data['limit'] ?? 50);

        return $this->success($this->logs->recent($channel, $limit), 'System logs retrieved.');
    }
}
