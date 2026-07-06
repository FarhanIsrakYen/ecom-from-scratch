<?php

namespace App\Services;

use App\Services\Support\PublicCacheStore;
use Closure;

class CategoryCacheService
{
    private const SCOPE = 'categories';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberTree(Closure $callback)
    {
        $version = $this->cache->version(self::SCOPE);
        $key = 'public:categories:tree:v'.$version;

        return $this->cache->remember(['categories'], $key, $callback);
    }

    public function flush(): void
    {
        $this->cache->flush(['categories'], self::SCOPE);
    }

    public function __construct(private readonly PublicCacheStore $cache) {}
}
