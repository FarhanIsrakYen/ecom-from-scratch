<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'type' => fake()->randomElement(['fixed', 'percentage']),
            'value' => fake()->randomFloat(2, 5, 25),
            'max_discount_amount' => null,
            'minimum_order_amount' => null,
            'usage_limit' => null,
            'usage_per_user' => null,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ];
    }
}
