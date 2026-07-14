<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 25, 500);
        $discount = fake()->randomFloat(2, 0, min(25, $subtotal));
        $delivery = fake()->randomFloat(2, 0, 20);
        $tax = fake()->randomFloat(2, 0, 30);

        return [
            'user_id' => User::factory(),
            'order_number' => fake()->unique()->bothify('ORD-########'),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'coupon_id' => null,
            'coupon_code' => null,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_charge' => $delivery,
            'tax' => $tax,
            'total' => $subtotal - $discount + $delivery + $tax,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => 'paid',
            'payment_status' => 'paid',
        ]);
    }
}
