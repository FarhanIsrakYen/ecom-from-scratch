<?php

namespace App\Services;

use App\Models\DeliveryZone;

class DeliveryChargeService
{
    public function calculate(array $address): float
    {
        $zone = $this->matchingZones($address)->first();

        if ($zone instanceof DeliveryZone) {
            return (float) $zone->charge;
        }

        $default = DeliveryZone::query()
            ->where('status', 'active')
            ->where('is_default', true)
            ->latest('id')
            ->first();

        return $default instanceof DeliveryZone ? (float) $default->charge : 0.0;
    }

    private function matchingZones(array $address)
    {
        $country = $address['country'] ?? null;
        $state = $address['state'] ?? null;
        $city = $address['city'] ?? null;
        $postalCode = $address['postal_code'] ?? null;

        return DeliveryZone::query()
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
            ->where(function ($query) use ($postalCode): void {
                $query->whereNull('postal_code')->orWhere('postal_code', $postalCode);
            })
            ->orderByRaw('CASE WHEN postal_code IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN city IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN state IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN country IS NULL THEN 0 ELSE 1 END DESC')
            ->latest('id')
            ->get();
    }
}
