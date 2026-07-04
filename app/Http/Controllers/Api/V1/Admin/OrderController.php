<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderStatusService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use ValueError;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderStatusService $statuses) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'payment_status' => ['sometimes', 'string', Rule::in(array_column(PaymentStatus::cases(), 'value'))],
            'customer' => ['sometimes', 'string'],
            'customer_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date'],
        ]);

        $orders = Order::query()
            ->with(['user', 'shipments'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, string $status) => $query->where('payment_status', $status))
            ->when($filters['customer_id'] ?? null, fn ($query, int $id) => $query->where('user_id', $id))
            ->when($filters['customer'] ?? null, function ($query, string $customer): void {
                $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('name', 'like', "%{$customer}%")
                    ->orWhere('email', 'like', "%{$customer}%"));
            })
            ->when($filters['from_date'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->get();

        return $this->success(OrderResource::collection($orders), 'Orders retrieved.');
    }

    public function show(Order $order): JsonResponse
    {
        return $this->success(
            new OrderResource($order->load(['user', 'items', 'addresses', 'payments', 'shipments', 'statusAudits'])),
            'Order retrieved.',
        );
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'allow_cancellation' => ['sometimes', 'boolean'],
            'shipment' => ['sometimes', 'array'],
            'shipment.id' => ['sometimes', 'integer', Rule::exists('shipments', 'id')],
            'shipment.courier_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipment.tracking_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shipment.shipped_at' => ['sometimes', 'date'],
            'shipment.delivered_at' => ['sometimes', 'date'],
        ]);

        try {
            $order = $this->statuses->transition($order, OrderStatus::from($data['status']), $request->user(), [
                'note' => $data['note'] ?? null,
                'allow_cancellation' => (bool) ($data['allow_cancellation'] ?? false),
                'shipment' => $data['shipment'] ?? [],
            ]);
        } catch (DomainException|ValueError $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success(new OrderResource($order), 'Order status updated.');
    }
}
