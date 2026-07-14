<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    public function definition(): array
    {
        return [
            'name' => fake()->city().' Zone',
            'country' => 'Bangladesh',
            'state' => null,
            'city' => null,
            'postal_code' => null,
            'charge' => fake()->randomFloat(2, 5, 20),
            'is_default' => false,
            'status' => 'active',
        ];
    }
}
