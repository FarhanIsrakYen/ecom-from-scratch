<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getCart(User $user): Cart
    {
        /** @var Cart $cart */
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        return $this->loadPricedCart($cart);
    }

    public function addItem(User $user, int $productId, ?int $variantId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $productId, $variantId, $quantity): Cart {
            $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
            $this->assertPurchasable($productId, $variantId);
            $this->assertStockAvailable($productId, $variantId, $quantity);

            /** @var CartItem|null $item */
            $item = $cart->items()
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->first();

            if ($item instanceof CartItem) {
                $newQuantity = $item->quantity + $quantity;
                $this->assertStockAvailable($productId, $variantId, $newQuantity);
                $item->update(['quantity' => $newQuantity]);
            } else {
                $cart->items()->create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ]);
            }

            return $this->loadPricedCart($cart);
        });
    }

    public function updateItem(User $user, CartItem $item, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $item, $quantity): Cart {
            $cart = $this->cartForUser($user, $item);
            $this->assertPurchasable($item->product_id, $item->product_variant_id);
            $this->assertStockAvailable($item->product_id, $item->product_variant_id, $quantity);

            $item->update(['quantity' => $quantity]);

            return $this->loadPricedCart($cart);
        });
    }

    public function removeItem(User $user, CartItem $item): Cart
    {
        return DB::transaction(function () use ($user, $item): Cart {
            $cart = $this->cartForUser($user, $item);
            $item->delete();

            return $this->loadPricedCart($cart);
        });
    }

    public function clear(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
            $cart->items()->delete();

            return $this->loadPricedCart($cart);
        });
    }

    public function loadPricedCart(Cart $cart): Cart
    {
        $cart->load(['items.product', 'items.variant.inventory']);

        $summary = $this->calculateSummary($cart->items);
        $cart->setRelation('items', $cart->items->map(function (CartItem $item): CartItem {
            $unitPrice = $this->unitPrice($item->product, $item->variant);
            $item->unit_price = number_format($unitPrice, 2, '.', '');
            $item->line_total = number_format($unitPrice * $item->quantity, 2, '.', '');

            return $item;
        }));
        $cart->summary = $summary;

        return $cart;
    }

    public function calculateSummary(Collection $items, float $deliveryCharge = 0, float $discount = 0, float $tax = 0): array
    {
        $subtotal = $items->sum(function (CartItem $item): float {
            return $this->unitPrice($item->product, $item->variant) * $item->quantity;
        });
        $total = max(0, $subtotal - $discount + $deliveryCharge + $tax);

        return [
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount' => number_format($discount, 2, '.', ''),
            'delivery_charge' => number_format($deliveryCharge, 2, '.', ''),
            'tax' => number_format($tax, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ];
    }

    public function unitPrice(Product $product, ?ProductVariant $variant): float
    {
        if ($variant instanceof ProductVariant) {
            return (float) $variant->price;
        }

        return (float) ($product->sale_price ?? $product->base_price);
    }

    public function assertStockAvailable(int $productId, ?int $variantId, int $quantity): void
    {
        $available = (int) Inventory::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->value('available_stock');

        if ($available < $quantity) {
            throw new CartException('Requested quantity is not available in stock.');
        }
    }

    private function assertPurchasable(int $productId, ?int $variantId): void
    {
        $product = Product::query()->whereKey($productId)->where('status', 'active')->first();

        if (! $product instanceof Product) {
            throw new CartException('Product is not available for purchase.');
        }

        if ($variantId === null) {
            return;
        }

        $variantExists = ProductVariant::query()
            ->whereKey($variantId)
            ->where('product_id', $productId)
            ->exists();

        if (! $variantExists) {
            throw new CartException('Variant is not available for this product.');
        }
    }

    private function cartForUser(User $user, CartItem $item): Cart
    {
        $item->loadMissing('cart');

        if ((int) $item->cart->user_id !== (int) $user->id) {
            throw new CartException('Cart item was not found.');
        }

        return $item->cart;
    }
}
