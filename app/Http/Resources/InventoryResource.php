<?php

namespace App\Http\Resources;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Inventory $inventory */
        $inventory = $this->resource;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'available_stock' => $this->available_stock,
            'reserved_stock' => $this->reserved_stock,
            'sold_stock' => $this->sold_stock,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_low_stock' => $inventory->isLowStock(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
