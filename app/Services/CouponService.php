<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;

class CouponService
{
    public function validateAndCalculate(?string $code, User $user, float $subtotal): array
    {
        if ($code === null || trim($code) === '') {
            return [
                'coupon' => null,
                'code' => null,
                'discount' => 0.0,
            ];
        }

        $normalizedCode = strtoupper(trim($code));

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()
            ->where('code', $normalizedCode)
            ->lockForUpdate()
            ->first();

        if (! $coupon instanceof Coupon) {
            throw new CartException('Coupon was not found.');
        }

        if ($coupon->status !== 'active') {
            throw new CartException('Coupon is not active.');
        }

        if ($coupon->starts_at !== null && now()->lt($coupon->starts_at)) {
            throw new CartException('Coupon is not active yet.');
        }

        if ($coupon->expires_at !== null && now()->gt($coupon->expires_at)) {
            throw new CartException('Coupon has expired.');
        }

        if ($coupon->minimum_order_amount !== null && $subtotal < (float) $coupon->minimum_order_amount) {
            throw new CartException('Minimum order amount was not reached for this coupon.');
        }

        if ($coupon->usage_limit !== null && $coupon->usages()->count() >= $coupon->usage_limit) {
            throw new CartException('Coupon usage limit has been reached.');
        }

        if (
            $coupon->usage_per_user !== null
            && $coupon->usages()->where('user_id', $user->id)->count() >= $coupon->usage_per_user
        ) {
            throw new CartException('Coupon usage limit has been reached for this customer.');
        }

        $discount = match ($coupon->type) {
            'fixed' => (float) $coupon->value,
            'percentage' => $subtotal * ((float) $coupon->value / 100),
            default => throw new CartException('Coupon type is invalid.'),
        };

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        $discount = min($discount, $subtotal);

        return [
            'coupon' => $coupon,
            'code' => $coupon->code,
            'discount' => round($discount, 2),
        ];
    }

    public function recordUsage(Coupon $coupon, User $user, Order $order, float $discount): void
    {
        $coupon->usages()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'discount_amount' => number_format($discount, 2, '.', ''),
        ]);
    }
}
