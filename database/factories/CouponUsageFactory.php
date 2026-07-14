<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponUsage>
 */
class CouponUsageFactory extends Factory
{
    protected $model = CouponUsage::class;

    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'coupon_id' => Coupon::factory(),
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'discount_amount' => fake()->randomFloat(2, 1, 20),
        ];
    }

    public function forUserOrder(User $user, Order $order): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'order_id' => $order->id,
        ]);
    }
}
