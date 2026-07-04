<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['items', 'addresses', 'shipments'])
            ->latest()
            ->get();

        return $this->success(OrderResource::collection($orders), 'Order history retrieved.');
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ((int) $order->user_id !== (int) $request->user()->id) {
            return $this->error('Order was not found.', 404);
        }

        return $this->success(
            new OrderResource($order->load(['items', 'addresses', 'shipments', 'statusAudits'])),
            'Order retrieved.',
        );
    }
}
