<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function paginate(array $filters = []): CursorPaginator
    {
        return $this->filteredQuery($filters, $filters['status'] ?? 'active')
            ->cursorPaginate($filters['per_page'] ?? 15);
    }

    public function adminPaginate(array $filters = []): CursorPaginator
    {
        return $this->filteredQuery($filters, $filters['status'] ?? null)
            ->cursorPaginate($filters['per_page'] ?? 15);
    }

    private function filteredQuery(array $filters, ?string $defaultStatus): Builder
    {
        return Product::query()
            ->with(['category', 'brand', 'primaryImages'])
            ->when($filters['category'] ?? null, function (Builder $query, string $category): void {
                $query->where(function (Builder $query) use ($category): void {
                    $query->where('category_id', $category)
                        ->orWhereIn('category_id', Category::query()
                            ->select('id')
                            ->where('slug', $category));
                });
            })
            ->when($filters['brand'] ?? null, function (Builder $query, string $brand): void {
                $query->where(function (Builder $query) use ($brand): void {
                    $query->where('brand_id', $brand)
                        ->orWhereIn('brand_id', Brand::query()
                            ->select('id')
                            ->where('slug', $brand));
                });
            })
            ->when($defaultStatus, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(array_key_exists('featured', $filters), fn (Builder $query) => $query->where('is_featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN)))
            ->when($filters['min_price'] ?? null, fn (Builder $query, mixed $price) => $query->where('base_price', '>=', $price))
            ->when($filters['max_price'] ?? null, fn (Builder $query, mixed $price) => $query->where('base_price', '<=', $price))
            ->tap(fn (Builder $query) => $this->applySorting($query, $filters['sort'] ?? 'latest'));
    }

    public function findBySlug(string $slug): Product
    {
        return Product::query()
            ->with(['category', 'brand', 'variants', 'images'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $variants = $data['variants'] ?? [];
            $images = $data['images'] ?? [];
            unset($data['variants'], $data['images']);

            /** @var Product $product */
            $product = Product::query()->create($data);

            if ($variants !== []) {
                $product->variants()->createMany($variants);
            }

            if ($images !== []) {
                $this->createImages($product, $images);
            }

            return $product->load(['category', 'brand', 'variants', 'images']);
        });
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->refresh()->load(['category', 'brand', 'variants', 'images']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    private function applySorting(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_low_to_high' => $query->orderBy('base_price')->orderBy('id'),
            'price_high_to_low' => $query->orderByDesc('base_price')->orderByDesc('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }

    private function createImages(Product $product, array $images): void
    {
        $hasPrimary = collect($images)->contains(fn (array $image): bool => (bool) ($image['is_primary'] ?? false));

        foreach ($images as $index => $image) {
            $product->images()->create([
                'image_path' => $image['image_path'],
                'is_primary' => $hasPrimary ? (bool) ($image['is_primary'] ?? false) : $index === 0,
                'sort_order' => $image['sort_order'] ?? $index,
            ]);
        }
    }
}
