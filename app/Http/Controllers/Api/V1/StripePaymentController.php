<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStripeCheckoutSessionRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class StripePaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentService $payments) {}

    public function createCheckoutSession(CreateStripeCheckoutSessionRequest $request): JsonResponse
    {
        try {
            $session = $this->payments->createStripeCheckoutSession(
                $request->user(),
                Order::query()->findOrFail($request->integer('order_id')),
            );
        } catch (CartException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success([
            'payment' => (new PaymentResource($session['payment']))->resolve(),
            'checkout_session_id' => $session['checkout_session_id'],
            'checkout_url' => $session['checkout_url'],
        ], 'Stripe checkout session created.', 201);
    }
}
