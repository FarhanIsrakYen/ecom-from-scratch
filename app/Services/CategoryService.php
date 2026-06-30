<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

class CategoryService
{
    /**
     * @return Collection<int, Category>
     */
    public function publicList(): Collection
    {
        return Category::query()
            ->with('children')
            ->where('status', 'active')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }

    public function paginate(array $filters = []): CursorPaginator
    {
        return Category::query()
            ->with('parent')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Category
    {
        return Category::query()->create($data)->load('parent');
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh()->load('parent');
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
