<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\CartException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProcessedWebhookEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;

class PaymentService
{
    public function __construct(
        private readonly StripePaymentGateway $stripe,
        private readonly StockReservationService $stockReservation,
        private readonly OrderStatusService $orderStatusService,
    ) {}

    public function createStripeCheckoutSession(User $user, Order $order): array
    {
        return DB::transaction(function () use ($user, $order): array {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ((int) $order->user_id !== (int) $user->id) {
                throw new CartException('Order was not found.');
            }

            if ($order->status !== OrderStatus::AwaitingPayment || $order->payment_status !== PaymentStatus::Pending) {
                throw new CartException('Order is not awaiting payment.');
            }

            /** @var Payment $payment */
            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'amount' => $order->total,
                'currency' => strtolower((string) config('services.stripe.currency', 'usd')),
                'status' => PaymentStatus::Pending->value,
                'metadata' => [
                    'order_number' => $order->order_number,
                ],
            ]);

            $payment->attempts()->create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'type' => 'checkout_session.create',
                'status' => PaymentStatus::Pending->value,
            ]);

            $session = $this->stripe->createCheckoutSession($order, $payment);

            $payment->update([
                'provider_checkout_session_id' => $session->id,
                'provider_payment_id' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
                'metadata' => [
                    ...($payment->metadata ?? []),
                    'checkout_url' => $session->url,
                ],
            ]);

            $payment->attempts()->create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_checkout_session_id' => $session->id,
                'provider_payment_id' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
                'type' => 'checkout_session.created',
                'status' => PaymentStatus::Pending->value,
                'payload' => $this->stripeObjectToArray($session),
            ]);

            return [
                'payment' => $payment->refresh(),
                'checkout_session_id' => $session->id,
                'checkout_url' => $session->url,
            ];
        });
    }

    public function handleStripeWebhook(array $event): bool
    {
        return DB::transaction(function () use ($event): bool {
            $eventId = (string) ($event['id'] ?? '');
            $eventType = (string) ($event['type'] ?? '');

            if ($eventId === '' || $eventType === '') {
                throw new CartException('Stripe webhook payload is invalid.');
            }

            if (ProcessedWebhookEvent::query()->where('provider', 'stripe')->where('event_id', $eventId)->exists()) {
                return false;
            }

            $object = $event['data']['object'] ?? [];
            if (! is_array($object)) {
                throw new CartException('Stripe webhook object is invalid.');
            }

            match ($eventType) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($eventId, $object),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($eventId, $object),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($eventId, $object),
                'charge.refunded', 'refund.created', 'refund.updated' => $this->handleRefunded($eventId, $eventType, $object),
                default => null,
            };

            ProcessedWebhookEvent::query()->create([
                'provider' => 'stripe',
                'event_id' => $eventId,
                'event_type' => $eventType,
                'processed_at' => now(),
            ]);

            return true;
        });
    }

    private function handleCheckoutSessionCompleted(string $eventId, array $session): void
    {
        $payment = $this->findPayment($session);

        if (! $payment instanceof Payment) {
            return;
        }

        $payment->update([
            'provider_checkout_session_id' => $session['id'] ?? $payment->provider_checkout_session_id,
            'provider_payment_id' => $session['payment_intent'] ?? $payment->provider_payment_id,
        ]);

        $this->recordWebhookAttempt($payment, $eventId, 'checkout.session.completed', $session, PaymentStatus::Paid->value);

        if (($session['payment_status'] ?? null) === 'paid') {
            $this->markPaid($payment);
        }
    }

    private function handlePaymentIntentSucceeded(string $eventId, array $intent): void
    {
        $payment = $this->findPayment($intent);

        if (! $payment instanceof Payment) {
            return;
        }

        $payment->update(['provider_payment_id' => $intent['id'] ?? $payment->provider_payment_id]);
        $this->recordWebhookAttempt($payment, $eventId, 'payment_intent.succeeded', $intent, PaymentStatus::Paid->value);
        $this->markPaid($payment);
    }

    private function handlePaymentIntentFailed(string $eventId, array $intent): void
    {
        $payment = $this->findPayment($intent);

        if (! $payment instanceof Payment) {
            return;
        }

        $payment->update(['provider_payment_id' => $intent['id'] ?? $payment->provider_payment_id]);
        $this->recordWebhookAttempt($payment, $eventId, 'payment_intent.payment_failed', $intent, PaymentStatus::Failed->value);
        $this->markFailed($payment);
    }

    private function handleRefunded(string $eventId, string $eventType, array $object): void
    {
        $payment = $this->findPayment($object);

        if (! $payment instanceof Payment) {
            return;
        }

        $this->recordWebhookAttempt($payment, $eventId, $eventType, $object, PaymentStatus::Refunded->value);
        $this->markRefunded($payment);
    }

    private function markPaid(Payment $payment): void
    {
        $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

        if (in_array($payment->status, [PaymentStatus::Paid->value, PaymentStatus::Failed->value, PaymentStatus::Refunded->value], true)) {
            return;
        }

        $order = Order::query()->with('items')->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

        foreach ($order->items as $item) {
            $this->stockReservation->confirmSold(
                $item->product_id,
                $item->product_variant_id,
                $item->quantity,
                'order',
                $order->id,
                $order->user_id,
            );
        }

        $payment->update(['status' => PaymentStatus::Paid->value]);
        $this->orderStatusService->transition($order, OrderStatus::Paid, null, [
            'payment_status' => PaymentStatus::Paid,
            'payment' => $payment->refresh(),
            'note' => 'Payment confirmed.',
        ]);
    }

    private function markFailed(Payment $payment): void
    {
        $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

        if ($payment->status === PaymentStatus::Failed->value || $payment->status === PaymentStatus::Paid->value) {
            return;
        }

        $order = Order::query()->with('items')->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

        foreach ($order->items as $item) {
            $this->stockReservation->release(
                $item->product_id,
                $item->product_variant_id,
                $item->quantity,
                'order',
                $order->id,
                $order->user_id,
            );
        }

        $payment->update(['status' => PaymentStatus::Failed->value]);
        $this->orderStatusService->transition($order, OrderStatus::Cancelled, null, [
            'allow_cancellation' => true,
            'payment_status' => PaymentStatus::Failed,
            'note' => 'Payment failed.',
        ]);
    }

    private function markRefunded(Payment $payment): void
    {
        $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

        if ($payment->status === PaymentStatus::Refunded->value) {
            return;
        }

        $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

        $payment->update(['status' => PaymentStatus::Refunded->value]);
        $this->orderStatusService->transition($order, OrderStatus::Refunded, null, [
            'refund_flow' => true,
            'payment_status' => PaymentStatus::Refunded,
            'payment' => $payment->refresh(),
            'note' => 'Payment refunded.',
        ]);
    }

    private function findPayment(array $object): ?Payment
    {
        $metadata = $object['metadata'] ?? [];
        $paymentId = $metadata['payment_id'] ?? null;

        if ($paymentId !== null) {
            return Payment::query()->whereKey($paymentId)->first();
        }

        $paymentIntent = $object['payment_intent'] ?? $object['id'] ?? null;
        $sessionId = (($object['object'] ?? null) === 'checkout.session') ? ($object['id'] ?? null) : null;

        if ($paymentIntent === null && $sessionId === null) {
            return null;
        }

        return Payment::query()
            ->when($paymentIntent !== null, fn ($query) => $query->orWhere('provider_payment_id', $paymentIntent))
            ->when($sessionId !== null, fn ($query) => $query->orWhere('provider_checkout_session_id', $sessionId))
            ->first();
    }

    private function recordWebhookAttempt(Payment $payment, string $eventId, string $type, array $payload, string $status): void
    {
        $payment->attempts()->create([
            'order_id' => $payment->order_id,
            'provider' => 'stripe',
            'provider_event_id' => $eventId,
            'provider_payment_id' => $payload['payment_intent'] ?? $payload['id'] ?? $payment->provider_payment_id,
            'provider_checkout_session_id' => (($payload['object'] ?? null) === 'checkout.session') ? ($payload['id'] ?? null) : $payment->provider_checkout_session_id,
            'type' => $type,
            'status' => $status,
            'payload' => $payload,
        ]);
    }

    private function stripeObjectToArray(Session $session): array
    {
        /** @var array $payload */
        $payload = $session->toArray();

        return $payload;
    }
}
