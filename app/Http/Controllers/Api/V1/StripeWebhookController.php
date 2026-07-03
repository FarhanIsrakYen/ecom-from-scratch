<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\StripePaymentGateway;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StripePaymentGateway $stripe,
        private readonly PaymentService $payments,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return $this->error('Invalid Stripe webhook signature.', 400);
        }

        $processed = $this->payments->handleStripeWebhook($event->toArray());

        return $this->success(['processed' => $processed], 'Stripe webhook processed.');
    }
}
