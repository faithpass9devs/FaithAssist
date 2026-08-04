<?php

namespace Tests\Feature\Catechism;

use App\Support\HostingerCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HostingerCacheTest extends TestCase
{
    public function test_remember_stores_values_under_the_hostinger_tag(): void
    {
        $result = Cache::tags(HostingerCache::tags())->remember(
            'probe',
            HostingerCache::ttl('all'),
            fn () => ['id' => 1]
        );

        $this->assertSame(['id' => 1], $result);
        $this->assertSame(
            ['id' => 1],
            Cache::tags(HostingerCache::tags())->get('probe')
        );
    }

    public function test_flush_clears_all_hostinger_entries(): void
    {
        Cache::tags(HostingerCache::tags())->put('probe', 'value', 60);

        HostingerCache::flush();

        $this->assertNull(Cache::tags(HostingerCache::tags())->get('probe'));
    }

    public function test_flush_does_not_clear_other_tags(): void
    {
        Cache::tags(['other'])->put('other-key', 'keep', 60);

        HostingerCache::flush();

        $this->assertSame('keep', Cache::tags(['other'])->get('other-key'));
    }

    public function test_ttl_returns_configured_values(): void
    {
        $this->assertSame(
            (int) config('hostinger_cache.ttl.all'),
            HostingerCache::ttl('all')
        );
        $this->assertSame(
            (int) config('hostinger_cache.ttl.filters'),
            HostingerCache::ttl('filters')
        );
    }

    public function test_command_clears_hostinger_cache(): void
    {
        Cache::tags(HostingerCache::tags())->put('probe', 'value', 60);

        $this->artisan('hostinger:cache:clear')
            ->expectsOutput('Caché de Hostinger limpiada.')
            ->assertSuccessful();

        $this->assertNull(Cache::tags(HostingerCache::tags())->get('probe'));
    }
}
