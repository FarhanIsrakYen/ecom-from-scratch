<?php

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class ProductImageService
{
    public function paginate(array $filters = []): CursorPaginator
    {
        return ProductImage::query()
            ->with('product')
            ->when($filters['product_id'] ?? null, fn ($query, mixed $productId) => $query->where('product_id', $productId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->cursorPaginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): ProductImage
    {
        return DB::transaction(function () use ($data): ProductImage {
            if ($data['is_primary'] ?? false) {
                ProductImage::query()
                    ->where('product_id', $data['product_id'])
                    ->update(['is_primary' => false]);
            }

            return ProductImage::query()->create($data);
        });
    }

    public function update(ProductImage $image, array $data): ProductImage
    {
        return DB::transaction(function () use ($image, $data): ProductImage {
            if ($data['is_primary'] ?? false) {
                ProductImage::query()
                    ->where('product_id', $data['product_id'] ?? $image->product_id)
                    ->whereKeyNot($image->id)
                    ->update(['is_primary' => false]);
            }

            $image->update($data);

            return $image->refresh();
        });
    }

    public function delete(ProductImage $image): void
    {
        $image->delete();
    }
}
