<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PopularSearch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSearchService
{
    public function search(array $filters, ?User $user = null): array
    {
        $query = $this->filteredQuery($filters)
            ->with(['category', 'brand', 'primaryImages']);

        $this->applySorting($query, $filters['sort'] ?? 'relevance', $filters['q'] ?? null);

        /** @var CursorPaginator $paginator */
        $paginator = $query->cursorPaginate($filters['per_page'] ?? 15);

        if ($this->hasKeyword($filters)) {
            $this->recordSearch($filters, $user);
        }

        return [
            'paginator' => $paginator,
            'facets' => $this->facets($filters),
            'suggestions' => $this->suggestions((string) ($filters['q'] ?? ''), $user),
            'popular_searches' => $this->popularSearches(),
        ];
    }

    public function suggestions(string $query = '', ?User $user = null, int $limit = 10): array
    {
        $term = $this->normalizeKeyword($query);

        $popular = PopularSearch::query()
            ->select('query')
            ->when($term !== '', fn (Builder $query) => $query->where('query', 'like', $term.'%'))
            ->orderByDesc('search_count')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->pluck('query');

        $history = collect();

        if ($user !== null) {
            $history = SearchHistory::query()
                ->where('user_id', $user->id)
                ->select('query')
                ->when($term !== '', fn (Builder $query) => $query->where('query', 'like', $term.'%'))
                ->latest()
                ->limit($limit)
                ->pluck('query');
        }

        $products = Product::query()
            ->where('status', 'active')
            ->select('name')
            ->when(
                $term !== '',
                fn (Builder $query) => $this->applyKeyword($query, $term, false),
                fn (Builder $query) => $query->latest(),
            )
            ->limit($limit)
            ->pluck('name');

        return $popular
            ->merge($history)
            ->merge($products)
            ->filter()
            ->unique(fn (string $value): string => Str::lower($value))
            ->take($limit)
            ->values()
            ->all();
    }

    public function history(User $user, int $limit = 20): array
    {
        return SearchHistory::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get(['query', 'filters', 'created_at'])
            ->map(fn (SearchHistory $history): array => [
                'query' => $history->query,
                'filters' => $history->filters ?? [],
                'created_at' => $history->created_at?->toISOString(),
            ])
            ->all();
    }

    public function popularSearches(int $limit = 10): array
    {
        return PopularSearch::query()
            ->orderByDesc('search_count')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->get(['query', 'search_count'])
            ->map(fn (PopularSearch $search): array => [
                'query' => $search->query,
                'search_count' => $search->search_count,
            ])
            ->all();
    }

    private function filteredQuery(array $filters): Builder
    {
        return Product::query()
            ->select('products.*')
            ->where('products.status', 'active')
            ->when($filters['q'] ?? null, fn (Builder $query, string $keyword) => $this->applyKeyword($query, $keyword))
            ->when($filters['category'] ?? null, function (Builder $query, string $category): void {
                $query->where(function (Builder $query) use ($category): void {
                    $query->where('products.category_id', $category)
                        ->orWhereIn('products.category_id', Category::query()
                            ->select('id')
                            ->where('slug', $category));
                });
            })
            ->when($filters['brand'] ?? null, function (Builder $query, string $brand): void {
                $query->where(function (Builder $query) use ($brand): void {
                    $query->where('products.brand_id', $brand)
                        ->orWhereIn('products.brand_id', Brand::query()
                            ->select('id')
                            ->where('slug', $brand));
                });
            })
            ->when($filters['min_price'] ?? null, fn (Builder $query, mixed $price) => $query->where('products.base_price', '>=', $price))
            ->when($filters['max_price'] ?? null, fn (Builder $query, mixed $price) => $query->where('products.base_price', '<=', $price))
            ->when($filters['availability'] ?? null, fn (Builder $query, string $availability) => $this->applyAvailability($query, $availability))
            ->when($filters['min_rating'] ?? null, fn (Builder $query, mixed $rating) => $query->where('products.average_rating', '>=', $rating))
            ->when($filters['attributes'] ?? null, fn (Builder $query, array $attributes) => $this->applyAttributes($query, $attributes));
    }

    private function applyKeyword(Builder $query, string $keyword, bool $selectRelevance = true): void
    {
        $keyword = $this->normalizeKeyword($keyword);

        if ($keyword === '') {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            if ($selectRelevance) {
                $query->selectRaw('MATCH(products.name, products.sku, products.short_description, products.description) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance', [$keyword]);
            }

            $query->whereRaw(
                'MATCH(products.name, products.sku, products.short_description, products.description) AGAINST (? IN BOOLEAN MODE)',
                [$this->booleanKeyword($keyword)],
            );

            return;
        }

        if ($selectRelevance) {
            $query->selectRaw('1 AS relevance');
        }

        $query->where(function (Builder $query) use ($keyword): void {
            foreach (['products.name', 'products.sku', 'products.short_description', 'products.description'] as $column) {
                $query->orWhere($column, 'like', '%'.$keyword.'%');
            }
        });
    }

    private function applyAvailability(Builder $query, string $availability): void
    {
        match ($availability) {
            'in_stock' => $query->whereHas('inventories', fn (Builder $query) => $query->where('available_stock', '>', 0)),
            'out_of_stock' => $query->whereDoesntHave('inventories', fn (Builder $query) => $query->where('available_stock', '>', 0)),
            default => null,
        };
    }

    private function applyAttributes(Builder $query, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_-]+$/', $key) || $value === null || $value === '') {
                continue;
            }

            $values = is_array($value) ? array_values($value) : [$value];

            $query->whereHas('variants', function (Builder $query) use ($key, $values): void {
                $query->where(function (Builder $query) use ($key, $values): void {
                    foreach ($values as $value) {
                        $query->orWhereJsonContains('attributes->'.$key, $value);
                    }
                });
            });
        }
    }

    private function applySorting(Builder $query, string $sort, ?string $keyword): void
    {
        match ($sort) {
            'latest' => $query->latest('products.created_at')->orderByDesc('products.id'),
            'price_low_to_high' => $query->orderBy('products.base_price')->orderBy('products.id'),
            'price_high_to_low' => $query->orderByDesc('products.base_price')->orderByDesc('products.id'),
            'most_popular' => $query->withSum('inventories as sold_units', 'sold_stock')
                ->orderByDesc('sold_units')
                ->orderByDesc('products.id'),
            default => $this->applyRelevanceSorting($query, $keyword),
        };
    }

    private function applyRelevanceSorting(Builder $query, ?string $keyword): void
    {
        if ($this->normalizeKeyword((string) $keyword) !== '') {
            $query->orderByDesc('relevance');
        }

        $query->latest('products.created_at')->orderByDesc('products.id');
    }

    private function facets(array $filters): array
    {
        $subquery = $this->matchingProductIds($filters);

        return [
            'brands' => $this->brandFacets($subquery),
            'categories' => $this->categoryFacets($subquery),
            'price_ranges' => $this->priceFacets($filters),
            'attributes' => $this->attributeFacets($subquery),
        ];
    }

    private function matchingProductIds(array $filters): QueryBuilder
    {
        return $this->filteredQuery($filters)
            ->reorder()
            ->select('products.id')
            ->toBase();
    }

    private function brandFacets(QueryBuilder $productIds): array
    {
        return Product::query()
            ->joinSub(clone $productIds, 'matched_products', fn ($join) => $join->on('products.id', '=', 'matched_products.id'))
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->groupBy('brands.id', 'brands.name', 'brands.slug')
            ->orderBy('brands.name')
            ->get([
                'brands.id',
                'brands.name',
                'brands.slug',
                DB::raw('COUNT(*) as count'),
            ])
            ->map(fn ($brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'count' => (int) $brand->count,
            ])
            ->all();
    }

    private function categoryFacets(QueryBuilder $productIds): array
    {
        return Product::query()
            ->joinSub(clone $productIds, 'matched_products', fn ($join) => $join->on('products.id', '=', 'matched_products.id'))
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderBy('categories.name')
            ->get([
                'categories.id',
                'categories.name',
                'categories.slug',
                DB::raw('COUNT(*) as count'),
            ])
            ->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => (int) $category->count,
            ])
            ->all();
    }

    private function priceFacets(array $filters): array
    {
        $ranges = [
            ['label' => 'Under 50', 'min' => null, 'max' => 50],
            ['label' => '50 to 100', 'min' => 50, 'max' => 100],
            ['label' => '100 to 250', 'min' => 100, 'max' => 250],
            ['label' => '250 to 500', 'min' => 250, 'max' => 500],
            ['label' => '500 and up', 'min' => 500, 'max' => null],
        ];

        return collect($ranges)
            ->map(function (array $range) use ($filters): array {
                $query = $this->filteredQuery(array_diff_key($filters, ['min_price' => true, 'max_price' => true]));

                if ($range['min'] !== null) {
                    $query->where('products.base_price', '>=', $range['min']);
                }

                if ($range['max'] !== null) {
                    $query->where('products.base_price', '<', $range['max']);
                }

                return [
                    ...$range,
                    'count' => $query->count(),
                ];
            })
            ->all();
    }

    private function attributeFacets(QueryBuilder $productIds): array
    {
        $attributes = [];

        ProductVariant::query()
            ->whereIn('product_id', clone $productIds)
            ->select(['id', 'attributes'])
            ->orderBy('id')
            ->chunkById(500, function ($variants) use (&$attributes): void {
                foreach ($variants as $variant) {
                    foreach (($variant->attributes ?? []) as $key => $value) {
                        if (! is_scalar($value)) {
                            continue;
                        }

                        $value = (string) $value;
                        $attributes[$key][$value] = ($attributes[$key][$value] ?? 0) + 1;
                    }
                }
            });

        ksort($attributes);

        return collect($attributes)
            ->map(fn (array $values): array => collect($values)
                ->sortKeys()
                ->map(fn (int $count, string $value): array => [
                    'value' => $value,
                    'count' => $count,
                ])
                ->values()
                ->all())
            ->all();
    }

    private function recordSearch(array $filters, ?User $user): void
    {
        $query = $this->normalizeKeyword((string) $filters['q']);

        $popular = PopularSearch::query()->firstOrNew(['query' => $query]);
        $popular->search_count = (int) $popular->search_count + 1;
        $popular->last_searched_at = Carbon::now();
        $popular->save();

        if ($user === null) {
            return;
        }

        SearchHistory::query()->create([
            'user_id' => $user->id,
            'query' => $query,
            'filters' => array_diff_key($filters, ['cursor' => true]),
        ]);
    }

    private function hasKeyword(array $filters): bool
    {
        return $this->normalizeKeyword((string) ($filters['q'] ?? '')) !== '';
    }

    private function normalizeKeyword(string $keyword): string
    {
        return Str::of($keyword)->squish()->limit(120, '')->toString();
    }

    private function booleanKeyword(string $keyword): string
    {
        return collect(explode(' ', $keyword))
            ->map(fn (string $term): string => '+'.preg_replace('/[^\pL\pN_-]+/u', '', $term).'*')
            ->filter(fn (string $term): bool => $term !== '+*')
            ->implode(' ');
    }
}
