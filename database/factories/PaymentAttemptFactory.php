<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        $payment = Payment::factory()->create();

        return [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'provider' => $payment->provider,
            'provider_event_id' => fake()->unique()->bothify('evt_test_########'),
            'provider_payment_id' => $payment->provider_payment_id,
            'provider_checkout_session_id' => $payment->provider_checkout_session_id,
            'type' => 'payment_intent.succeeded',
            'status' => 'paid',
            'payload' => [],
        ];
    }
}
