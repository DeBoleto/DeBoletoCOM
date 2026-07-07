<?php

namespace App\Shared\Traits;

use App\Shared\Cache\CacheStrategy;

trait HasRedisCache
{
    protected function remember(string $key, int $ttl, callable $callback): mixed
    {
        return CacheStrategy::remember(static::CACHE_TAG, $key, $ttl, $callback);
    }

    protected function forgetCache(): void
    {
        CacheStrategy::forget(static::CACHE_TAG);
    }
}
