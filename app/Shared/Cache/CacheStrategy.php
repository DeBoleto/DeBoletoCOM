<?php

namespace App\Shared\Cache;

use Illuminate\Support\Facades\Cache;

class CacheStrategy
{
    public static function remember(string $tag, string $key, int $ttl, callable $callback): mixed
    {
        return Cache::tags([$tag])->remember($key, $ttl, $callback);
    }

    public static function forget(string $tag): void
    {
        Cache::tags([$tag])->flush();
    }
}
