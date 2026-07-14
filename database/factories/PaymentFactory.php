<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'stripe',
            'provider_payment_id' => fake()->unique()->bothify('pi_test_########'),
            'provider_checkout_session_id' => fake()->unique()->bothify('cs_test_########'),
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'usd',
            'status' => 'pending',
            'metadata' => [],
        ];
    }
}
