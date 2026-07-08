<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\KnowledgeBase\ShoppingAssistantService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingAssistantController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ShoppingAssistantService $assistant) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $result = $this->assistant->answer($data['question']);

        return $this->success([
            'answer' => $result['answer'],
            'relevant' => $result['relevant'],
            'sources' => $result['sources'],
        ], $result['relevant'] ? 'Assistant answer generated.' : 'Question could not be answered from available data.');
    }
}
