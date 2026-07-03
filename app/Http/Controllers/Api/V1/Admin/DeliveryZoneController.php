<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreDeliveryZoneRequest;
use App\Http\Requests\Pricing\UpdateDeliveryZoneRequest;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\DeliveryZone;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $zones = DeliveryZone::query()
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->latest()
            ->get();

        return $this->success(DeliveryZoneResource::collection($zones), 'Delivery zones retrieved.');
    }

    public function store(StoreDeliveryZoneRequest $request): JsonResponse
    {
        return $this->success(
            new DeliveryZoneResource(DeliveryZone::query()->create($request->validated())),
            'Delivery zone created.',
            201,
        );
    }

    public function show(DeliveryZone $deliveryZone): JsonResponse
    {
        return $this->success(new DeliveryZoneResource($deliveryZone), 'Delivery zone retrieved.');
    }

    public function update(UpdateDeliveryZoneRequest $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->update($request->validated());

        return $this->success(new DeliveryZoneResource($deliveryZone->refresh()), 'Delivery zone updated.');
    }

    public function destroy(DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->delete();

        return $this->success(null, 'Delivery zone deleted.');
    }
}
