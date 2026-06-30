<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

class BrandService
{
    /**
     * @return Collection<int, Brand>
     */
    public function publicList(): Collection
    {
        return Brand::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function paginate(array $filters = []): CursorPaginator
    {
        return Brand::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Brand
    {
        return Brand::query()->create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand->refresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}
