<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly StockReservationService $stockReservation,
    ) {}

    public function checkout(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            $cart = $this->cartService->getCart($user);

            if ($cart->items->isEmpty()) {
                throw new CartException('Cart is empty.');
            }

            foreach ($cart->items as $item) {
                $this->cartService->assertStockAvailable($item->product_id, $item->product_variant_id, $item->quantity);
            }

            $deliveryCharge = (float) ($data['delivery_charge'] ?? 0);
            $discount = 0.0;
            $tax = 0.0;
            $totals = $this->cartService->calculateSummary($cart->items, $deliveryCharge, $discount, $tax);
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
            $cart->items()->delete();

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
