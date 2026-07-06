<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly Order $order,
        private readonly string $type,
    ) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order '.$this->order->order_number.' update')
            ->line('Your order status is now '.$this->order->status->value.'.')
            ->line('Order total: '.$this->order->total)
            ->line('Update type: '.$this->type);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order '.$this->order->order_number.' update',
            'message' => 'Your order status is now '.$this->order->status->value.'.',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value,
            'type' => $this->type,
        ];
    }
}
