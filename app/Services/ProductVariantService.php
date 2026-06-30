<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Pagination\CursorPaginator;

class ProductVariantService
{
    public function paginate(array $filters = []): CursorPaginator
    {
        return ProductVariant::query()
            ->with('product')
            ->when($filters['product_id'] ?? null, fn ($query, mixed $productId) => $query->where('product_id', $productId))
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): ProductVariant
    {
        return ProductVariant::query()->create($data);
    }

    public function update(ProductVariant $variant, array $data): ProductVariant
    {
        $variant->update($data);

        return $variant->refresh();
    }

    public function delete(ProductVariant $variant): void
    {
        $variant->delete();
    }
}
