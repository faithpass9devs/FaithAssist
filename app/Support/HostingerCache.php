<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Helper centralizado para la caché de los datos externos de Hostinger.
 */
class HostingerCache
{
    public static function tags(): array
    {
        return config('hostinger_cache.tags', ['hostinger']);
    }

    public static function ttl(string $key): int
    {
        return (int) config("hostinger_cache.ttl.{$key}", 900);
    }

    public static function flush(): void
    {
        Cache::tags(self::tags())->flush();
    }
}
