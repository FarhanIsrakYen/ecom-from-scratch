<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreCouponRequest;
use App\Http\Requests\Pricing\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $coupons = Coupon::query()
            ->when($filters['status'] ?? null, fn ($query, mixed $status) => $query->where('status', $status))
            ->latest()
            ->get();

        return $this->success(CouponResource::collection($coupons), 'Coupons retrieved.');
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        return $this->success(
            new CouponResource(Coupon::query()->create($request->validated())),
            'Coupon created.',
            201,
        );
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return $this->success(new CouponResource($coupon), 'Coupon retrieved.');
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->validated());

        return $this->success(new CouponResource($coupon->refresh()), 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return $this->success(null, 'Coupon deleted.');
    }
}
