<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'courier_name' => fake()->company(),
            'tracking_number' => fake()->unique()->bothify('TRK-########'),
            'status' => 'pending',
            'shipped_at' => null,
            'delivered_at' => null,
        ];
    }
}
