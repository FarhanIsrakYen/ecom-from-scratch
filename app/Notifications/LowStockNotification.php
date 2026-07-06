<?php

namespace App\Notifications;

use App\Models\Inventory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private readonly Inventory $inventory) {}

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
            ->subject('Low stock detected')
            ->line('A product inventory item is below its configured low stock threshold.')
            ->line('Product ID: '.$this->inventory->product_id)
            ->line('Variant ID: '.($this->inventory->product_variant_id ?? 'base product'))
            ->line('Available stock: '.$this->inventory->available_stock);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock',
            'title' => 'Low stock detected',
            'message' => 'Inventory is below its configured threshold.',
            'inventory_id' => $this->inventory->id,
            'product_id' => $this->inventory->product_id,
            'product_variant_id' => $this->inventory->product_variant_id,
            'available_stock' => $this->inventory->available_stock,
            'low_stock_threshold' => $this->inventory->low_stock_threshold,
        ];
    }
}
