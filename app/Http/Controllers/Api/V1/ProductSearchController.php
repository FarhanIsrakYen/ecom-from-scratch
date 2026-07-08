<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ProductSearchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductSearchService $search) {}

    public function suggestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
        ]);

        return $this->success(
            $this->search->suggestions((string) ($data['q'] ?? ''), $request->user('sanctum')),
            'Search suggestions retrieved.',
        );
    }

    public function history(Request $request): JsonResponse
    {
        return $this->success(
            $this->search->history($request->user()),
            'Search history retrieved.',
        );
    }

    public function popular(): JsonResponse
    {
        return $this->success(
            $this->search->popularSearches(),
            'Popular searches retrieved.',
        );
    }
}
