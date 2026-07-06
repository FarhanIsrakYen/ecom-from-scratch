<?php

namespace App\Services\Support;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublicCacheStore
{
    /**
     * @template T
     *
     * @param  array<int, string>  $tags
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(array $tags, string $key, Closure $callback)
    {
        $seconds = (int) config('cache.public_ttl', 600);

        try {
            $repository = $this->repository((string) config('cache.public_store', 'redis'));

            return $this->taggedRepository($repository, $tags)->remember($key, $seconds, $callback);
        } catch (Throwable) {
            return $this->rememberFallback($tags, $key, $seconds, $callback);
        }
    }

    public function version(string $scope): int
    {
        $key = $this->versionKey($scope);

        try {
            return (int) $this->repository((string) config('cache.public_store', 'redis'))->get($key, 1);
        } catch (Throwable) {
            try {
                return (int) $this->fallbackRepository()->get($key, 1);
            } catch (Throwable) {
                return 1;
            }
        }
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function flush(array $tags, string $scope): void
    {
        foreach ([$this->publicStoreName(), $this->fallbackStoreName()] as $store) {
            try {
                $repository = $this->repository($store);

                if ($repository->supportsTags()) {
                    $repository->tags($tags)->flush();
                }
            } catch (Throwable) {
                //
            }
        }

        $this->bumpVersion($scope);
    }

    /**
     * @template T
     *
     * @param  array<int, string>  $tags
     * @param  Closure(): T  $callback
     * @return T
     */
    private function rememberFallback(array $tags, string $key, int $seconds, Closure $callback)
    {
        try {
            $repository = $this->fallbackRepository();

            return $this->taggedRepository($repository, $tags)->remember($key, $seconds, $callback);
        } catch (Throwable) {
            return $callback();
        }
    }

    private function bumpVersion(string $scope): void
    {
        $key = $this->versionKey($scope);

        foreach ([$this->publicStoreName(), $this->fallbackStoreName()] as $store) {
            try {
                $repository = $this->repository($store);
                $repository->add($key, 1);
                $repository->increment($key);
            } catch (Throwable) {
                //
            }
        }
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function taggedRepository(Repository $repository, array $tags): Repository
    {
        if ($tags !== [] && $repository->supportsTags()) {
            return $repository->tags($tags);
        }

        return $repository;
    }

    private function fallbackRepository(): Repository
    {
        return $this->repository($this->fallbackStoreName());
    }

    private function repository(string $store): Repository
    {
        return Cache::store($store);
    }

    private function publicStoreName(): string
    {
        return (string) config('cache.public_store', 'redis');
    }

    private function fallbackStoreName(): string
    {
        return (string) config('cache.public_fallback_store', config('cache.default', 'database'));
    }

    private function versionKey(string $scope): string
    {
        return "public-cache:{$scope}:version";
    }
}
