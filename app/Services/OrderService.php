<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;

class OrderService
{
    public function createPendingOrder(User $user, array $totals): Order
    {
        return Order::query()->create([
            'user_id' => $user->id,
            'order_number' => $this->generateOrderNumber(),
            'status' => OrderStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::Pending,
            'coupon_id' => $totals['coupon']?->id,
            'coupon_code' => $totals['coupon_code'],
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'delivery_charge' => $totals['delivery_charge'],
            'tax' => $totals['tax'],
            'total' => $totals['total'],
        ]);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
