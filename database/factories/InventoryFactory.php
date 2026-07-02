<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'available_stock' => fake()->numberBetween(0, 100),
            'reserved_stock' => 0,
            'sold_stock' => 0,
            'low_stock_threshold' => 5,
        ];
    }

    public function forVariant(?ProductVariant $variant = null): static
    {
        return $this->state(function () use ($variant): array {
            $variant ??= ProductVariant::factory()->create();

            return [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
            ];
        });
    }
}
