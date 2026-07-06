<?php

namespace App\Services;

use App\Services\Support\PublicCacheStore;
use Closure;

class ProductCacheService
{
    private const PRODUCTS_SCOPE = 'products';

    private const BRANDS_SCOPE = 'brands';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberListing(array $filters, Closure $callback)
    {
        $version = $this->cache->version(self::PRODUCTS_SCOPE);
        $key = 'public:products:list:v'.$version.':'.$this->hashFilters($filters);

        return $this->cache->remember(['products'], $key, $callback);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberFeatured(array $filters, Closure $callback)
    {
        $version = $this->cache->version(self::PRODUCTS_SCOPE);
        $key = 'public:products:featured:v'.$version.':'.$this->hashFilters($filters);

        return $this->cache->remember(['products', 'featured-products'], $key, $callback);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberDetail(string $slug, Closure $callback)
    {
        $version = $this->cache->version(self::PRODUCTS_SCOPE);
        $key = 'public:products:detail:v'.$version.':'.$slug;

        return $this->cache->remember(['products', 'product-detail'], $key, $callback);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberBrandList(Closure $callback)
    {
        $version = $this->cache->version(self::BRANDS_SCOPE);
        $key = 'public:brands:list:v'.$version;

        return $this->cache->remember(['brands'], $key, $callback);
    }

    public function flushProducts(): void
    {
        $this->cache->flush(['products'], self::PRODUCTS_SCOPE);
    }

    public function flushBrands(): void
    {
        $this->cache->flush(['brands'], self::BRANDS_SCOPE);
        $this->flushProducts();
    }

    public function __construct(private readonly PublicCacheStore $cache) {}

    private function hashFilters(array $filters): string
    {
        ksort($filters);

        return sha1(json_encode($filters, JSON_THROW_ON_ERROR));
    }
}
