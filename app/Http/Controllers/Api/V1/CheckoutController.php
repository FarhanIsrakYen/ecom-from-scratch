<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CartException;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CheckoutService $checkout) {}

    public function store(CheckoutRequest $request): JsonResponse
    {
        try {
            $order = $this->checkout->checkout($request->user(), $request->validated());
        } catch (CartException|InsufficientStockException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(new OrderResource($order), 'Checkout completed.', 201);
    }
}
