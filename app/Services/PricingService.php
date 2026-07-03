<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Support\Collection;

class PricingService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CouponService $couponService,
        private readonly DeliveryChargeService $deliveryChargeService,
    ) {}

    public function calculate(Collection $items, User $user, array $shippingAddress, ?string $couponCode = null): array
    {
        $subtotal = $items->sum(function (CartItem $item): float {
            return $this->cartService->unitPrice($item->product, $item->variant) * $item->quantity;
        });

        $coupon = $this->couponService->validateAndCalculate($couponCode, $user, $subtotal);
        $deliveryCharge = $this->deliveryChargeService->calculate($shippingAddress);
        $tax = $this->calculateTax(max(0, $subtotal - $coupon['discount']), $shippingAddress);
        $total = max(0, $subtotal - $coupon['discount'] + $deliveryCharge + $tax);

        return [
            'coupon' => $coupon['coupon'],
            'coupon_code' => $coupon['code'],
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount' => number_format($coupon['discount'], 2, '.', ''),
            'delivery_charge' => number_format($deliveryCharge, 2, '.', ''),
            'tax' => number_format($tax, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ];
    }

    private function calculateTax(float $taxableAmount, array $address): float
    {
        $taxSetting = $this->matchingTaxSetting($address)
            ?? TaxSetting::query()
                ->where('status', 'active')
                ->where('is_default', true)
                ->latest('id')
                ->first();

        if (! $taxSetting instanceof TaxSetting) {
            return 0.0;
        }

        return round($taxableAmount * ((float) $taxSetting->rate / 100), 2);
    }

    private function matchingTaxSetting(array $address): ?TaxSetting
    {
        $country = $address['country'] ?? null;
        $state = $address['state'] ?? null;
        $city = $address['city'] ?? null;

        return TaxSetting::query()
            ->where('status', 'active')
            ->where('is_default', false)
            ->where(function ($query) use ($country): void {
                $query->whereNull('country')->orWhere('country', $country);
            })
            ->where(function ($query) use ($state): void {
                $query->whereNull('state')->orWhere('state', $state);
            })
            ->where(function ($query) use ($city): void {
                $query->whereNull('city')->orWhere('city', $city);
            })
            ->orderByRaw('CASE WHEN city IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN state IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN country IS NULL THEN 0 ELSE 1 END DESC')
            ->latest('id')
            ->first();
    }
}
