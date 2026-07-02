<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Pagination\CursorPaginator;

class ProductVariantService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function paginate(array $filters = []): CursorPaginator
    {
        return ProductVariant::query()
            ->with(['product', 'inventory'])
            ->when($filters['product_id'] ?? null, fn ($query, mixed $productId) => $query->where('product_id', $productId))
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): ProductVariant
    {
        $initialStock = (int) ($data['stock'] ?? 0);
        $data['stock'] = 0;

        /** @var ProductVariant $variant */
        $variant = ProductVariant::query()->create($data);

        if ($initialStock > 0) {
            $this->inventory->increaseStock(
                $variant->product_id,
                $variant->id,
                $initialStock,
                'Initial variant stock',
                'product_variant',
                $variant->id,
            );
        }

        return $variant->refresh()->load('inventory');
    }

    public function update(ProductVariant $variant, array $data): ProductVariant
    {
        $stock = $data['stock'] ?? null;
        unset($data['stock']);

        $variant->update($data);

        if ($stock !== null) {
            $this->inventory->setAvailableStock(
                $variant->product_id,
                $variant->id,
                (int) $stock,
                'Admin stock adjustment',
                'product_variant',
                $variant->id,
            );
        }

        return $variant->refresh()->load('inventory');
    }

    public function delete(ProductVariant $variant): void
    {
        $variant->delete();
    }
}
