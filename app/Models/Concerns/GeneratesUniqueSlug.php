<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    protected static function bootGeneratesUniqueSlug(): void
    {
        static::saving(function (self $model): void {
            if (! $model->isDirty('name') && filled($model->slug)) {
                return;
            }

            $model->slug = $model->makeUniqueSlug($model->name, $model->getKey());
        });
    }

    private function makeUniqueSlug(string $name, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $counter = 2;

        while ($this->newQuery()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
