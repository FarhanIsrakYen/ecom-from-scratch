<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripePaymentGateway
{
    public function createCheckoutSession(Order $order, Payment $payment): Session
    {
        $client = new StripeClient((string) config('services.stripe.secret'));

        return $client->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => config('services.stripe.success_url'),
            'cancel_url' => config('services.stripe.cancel_url'),
            'client_reference_id' => (string) $order->id,
            'payment_intent_data' => [
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'payment_id' => (string) $payment->id,
                ],
            ],
            'metadata' => [
                'order_id' => (string) $order->id,
                'payment_id' => (string) $payment->id,
            ],
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $payment->currency,
                        'unit_amount' => (int) round((float) $payment->amount * 100),
                        'product_data' => [
                            'name' => 'Order '.$order->order_number,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @throws SignatureVerificationException
     * @throws UnexpectedValueException
     */
    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            (string) config('services.stripe.webhook_secret'),
        );
    }
}
