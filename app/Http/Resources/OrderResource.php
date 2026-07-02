<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'delivery_charge' => $this->delivery_charge,
            'tax' => $this->tax,
            'total' => $this->total,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'addresses' => OrderAddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
