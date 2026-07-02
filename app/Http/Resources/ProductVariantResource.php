<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'attributes' => $this->attributes,
            'price' => $this->price,
            'stock' => $this->whenLoaded('inventory', fn () => $this->inventory?->available_stock, $this->stock),
            'inventory' => $this->whenLoaded('inventory', fn () => [
                'available_stock' => $this->inventory?->available_stock,
                'reserved_stock' => $this->inventory?->reserved_stock,
                'sold_stock' => $this->inventory?->sold_stock,
                'low_stock_threshold' => $this->inventory?->low_stock_threshold,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
