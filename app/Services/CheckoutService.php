<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Jobs\SendOrderStatusNotification;
use App\Exceptions\CartException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly StockReservationService $stockReservation,
        private readonly PricingService $pricingService,
        private readonly CouponService $couponService,
        private readonly OrderStatusService $orderStatusService,
    ) {}

    public function checkout(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $cart = $this->cartService->getCart($user);

            if ($cart->items->isEmpty()) {
                throw new CartException('Cart is empty.');
            }

            foreach ($cart->items as $item) {
                $this->cartService->assertPurchasable($item->product_id, $item->product_variant_id);
                $this->cartService->assertStockAvailable($item->product_id, $item->product_variant_id, $item->quantity);
            }

            $totals = $this->pricingService->calculate(
                $cart->items,
                $user,
                $data['shipping_address'],
                $data['coupon_code'] ?? null,
            );
            $order = $this->orderService->createPendingOrder($user, $totals);

            foreach ($cart->items as $item) {
                $this->createOrderItem($order, $item);
                $this->stockReservation->reserve(
                    $item->product_id,
                    $item->product_variant_id,
                    $item->quantity,
                    'order',
                    $order->id,
                    $user->id,
                );
            }

            $this->createAddresses($order, $data);
            if ($totals['coupon'] !== null && (float) $totals['discount'] > 0) {
                $this->couponService->recordUsage($totals['coupon'], $user, $order, (float) $totals['discount']);
            }
            $cart->items()->delete();

            $order = $this->orderStatusService->transition($order, OrderStatus::AwaitingPayment, null, [
                'payment_status' => PaymentStatus::Pending,
                'note' => 'Order placed and awaiting payment.',
            ]);

            Event::dispatch(new OrderPlaced($order));
            SendOrderStatusNotification::dispatch($order->id, 'placed');

            return $order->load(['items', 'addresses']);
        });
    }

    private function createOrderItem(Order $order, CartItem $item): void
    {
        $unitPrice = $this->cartService->unitPrice($item->product, $item->variant);

        $order->items()->create([
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'product_name' => $item->product->name,
            'sku' => $item->variant?->sku ?? $item->product->sku,
            'variant_attributes' => $item->variant?->attributes,
            'quantity' => $item->quantity,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'line_total' => number_format($unitPrice * $item->quantity, 2, '.', ''),
        ]);
    }

    private function createAddresses(Order $order, array $data): void
    {
        $shipping = $data['shipping_address'];
        $billing = $data['billing_address'] ?? $shipping;

        $order->addresses()->create($shipping + ['type' => 'shipping']);
        $order->addresses()->create($billing + ['type' => 'billing']);
    }
}
