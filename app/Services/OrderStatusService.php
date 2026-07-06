<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderCancelled;
use App\Events\OrderDelivered;
use App\Events\OrderPaid;
use App\Events\OrderProcessingStarted;
use App\Events\OrderRefunded;
use App\Events\OrderShipped;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class OrderStatusService
{
    public function transition(
        Order $order,
        OrderStatus $to,
        ?User $actor = null,
        array $options = [],
    ): Order {
        return DB::transaction(function () use ($order, $to, $actor, $options): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $order->status;

            if ($from === $to) {
                return $order->load(['items', 'addresses', 'shipments', 'statusAudits']);
            }

            $this->assertTransitionAllowed($from, $to, $options);

            $updates = ['status' => $to];
            if (isset($options['payment_status']) && $options['payment_status'] instanceof PaymentStatus) {
                $updates['payment_status'] = $options['payment_status'];
            }

            $order->update($updates);

            $shipment = $this->syncShipment($order, $to, $options['shipment'] ?? []);

            $order->statusAudits()->create([
                'changed_by' => $actor?->id,
                'from_status' => $from?->value,
                'to_status' => $to->value,
                'note' => $options['note'] ?? null,
                'metadata' => $options['metadata'] ?? null,
            ]);

            $order = $order->refresh()->load(['items', 'addresses', 'shipments', 'statusAudits']);
            $this->dispatchStatusEvent($order, $to, $shipment, $options['payment'] ?? null);

            return $order;
        });
    }

    private function assertTransitionAllowed(OrderStatus $from, OrderStatus $to, array $options): void
    {
        if ($to === OrderStatus::Refunded && ! ($options['refund_flow'] ?? false)) {
            throw new DomainException('Order can only be refunded through the refund flow.');
        }

        if ($to === OrderStatus::Cancelled && ! ($options['allow_cancellation'] ?? false)) {
            throw new DomainException('Order cancellation is not allowed from the current flow.');
        }

        $allowed = match ($from) {
            OrderStatus::Pending => [OrderStatus::AwaitingPayment],
            OrderStatus::AwaitingPayment => ($options['allow_cancellation'] ?? false)
                ? [OrderStatus::Paid, OrderStatus::Cancelled]
                : [OrderStatus::Paid],
            OrderStatus::Paid => ($options['allow_cancellation'] ?? false)
                ? [OrderStatus::Processing, OrderStatus::Cancelled, OrderStatus::Refunded]
                : [OrderStatus::Processing, OrderStatus::Refunded],
            OrderStatus::Processing => ($options['allow_cancellation'] ?? false)
                ? [OrderStatus::Shipped, OrderStatus::Cancelled]
                : [OrderStatus::Shipped],
            OrderStatus::Shipped => [OrderStatus::Delivered],
            OrderStatus::Delivered => [OrderStatus::Refunded],
            OrderStatus::Cancelled, OrderStatus::Refunded => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new DomainException("Invalid order status transition from {$from->value} to {$to->value}.");
        }
    }

    private function syncShipment(Order $order, OrderStatus $to, array $shipmentData): ?Shipment
    {
        if ($to === OrderStatus::Shipped) {
            $values = [
                'courier_name' => $shipmentData['courier_name'] ?? null,
                'tracking_number' => $shipmentData['tracking_number'] ?? null,
                'status' => 'shipped',
                'shipped_at' => $shipmentData['shipped_at'] ?? now(),
            ];

            if (isset($shipmentData['id'])) {
                return $order->shipments()->updateOrCreate(['id' => $shipmentData['id']], $values);
            }

            return $order->shipments()->create($values);
        }

        if ($to === OrderStatus::Delivered) {
            $shipment = $order->shipments()->latest()->first();
            if ($shipment instanceof Shipment) {
                $shipment->update([
                    'status' => 'delivered',
                    'delivered_at' => $shipmentData['delivered_at'] ?? now(),
                ]);
            }

            return $shipment;
        }

        return null;
    }

    private function dispatchStatusEvent(Order $order, OrderStatus $status, ?Shipment $shipment, ?Payment $payment): void
    {
        match ($status) {
            OrderStatus::Paid => $payment instanceof Payment ? Event::dispatch(new OrderPaid($order, $payment)) : null,
            OrderStatus::Processing => Event::dispatch(new OrderProcessingStarted($order)),
            OrderStatus::Shipped => Event::dispatch(new OrderShipped($order, $shipment)),
            OrderStatus::Delivered => Event::dispatch(new OrderDelivered($order)),
            OrderStatus::Cancelled => Event::dispatch(new OrderCancelled($order)),
            OrderStatus::Refunded => $payment instanceof Payment ? Event::dispatch(new OrderRefunded($order, $payment)) : null,
            default => null,
        };
    }
}
