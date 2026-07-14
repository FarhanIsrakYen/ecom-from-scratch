<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        $variant = ProductVariant::factory()->create();

        return [
            'cart_id' => Cart::factory(),
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }

    public function forProduct(?Product $product = null, ?ProductVariant $variant = null): static
    {
        return $this->state(function () use ($product, $variant): array {
            $variant ??= ProductVariant::factory()->create([
                'product_id' => $product?->id ?? Product::factory(),
            ]);

            return [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
            ];
        });
    }
}
