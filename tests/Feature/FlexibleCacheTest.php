<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tests for Cache::flexible() (stale-while-revalidate) on alternative stores.
 */
class FlexibleCacheTest extends TestCase
{
    public static function storesProvider(): array
    {
        $stores = [
            'redis' => ['redis'],
            'file' => ['file'],
            'hierarchical_file' => ['hierarchical_file'],
            'array' => ['array'],
        ];
        if (static::isMemcachedAvailable()) {
            $stores['memcached'] = ['memcached'];
        }
        return $stores;
    }

    private static function isMemcachedAvailable(): bool
    {
        if (!class_exists('\Memcached')) {
            return false;
        }
        $socket = @fsockopen(getenv('MEMCACHED_HOST') ?: '127.0.0.1', (int)(getenv('MEMCACHED_PORT') ?: 11211), $errno, $errstr, 0.2);
        if ($socket === false) {
            return false;
        }
        fclose($socket);
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Run deferred callbacks (flexible() refresh) immediately.
        $this->withoutDefer();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        foreach (array_keys(static::storesProvider()) as $storeName) {
            $this->store($storeName)->flush();
        }
        parent::tearDown();
    }

    private function store(string $name): Repository
    {
        /** @var CacheManager $manager */
        $manager = $this->app['cache'];
        return $manager->store($name);
    }

    #[DataProvider('storesProvider')]
    public function testManyReturnsValuesByOriginalKeys(string $storeName): void
    {
        $store = $this->store($storeName);
        $store->put('pool:region', 'value1', 3600);
        $store->put('key|sub/key\\x', 'value2', 3600);

        static::assertSame(
            [
                'pool:region' => 'value1',
                'missing:key' => null,
                'key|sub/key\\x' => 'value2',
            ],
            $store->many(['pool:region', 'missing:key', 'key|sub/key\\x'])
        );
        static::assertSame(
            ['missing:key' => 'default', 'pool:region' => 'value1'],
            $store->many(['missing:key' => 'default', 'pool:region' => 'other'])
        );
    }

    #[DataProvider('storesProvider')]
    public function testManyWithKeysMappedToSameStorageKey(string $storeName): void
    {
        $store = $this->store($storeName);
        // For file stores both keys map to the same storage key.
        $store->put('same:key', 'value', 3600);

        $result = $store->many(['same:key', 'same_key']);
        static::assertArrayHasKey('same:key', $result);
        static::assertArrayHasKey('same_key', $result);
        static::assertSame('value', $result['same:key']);
        static::assertSame($store->get('same_key'), $result['same_key']);
    }

    #[DataProvider('storesProvider')]
    public function testFlexibleReturnsFreshValue(string $storeName): void
    {
        $store = $this->store($storeName);
        Carbon::setTestNow(Carbon::now());

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'value' . $calls;
        };

        static::assertSame('value1', $store->flexible('flexible:key', [60, 120], $callback));
        static::assertSame(1, $calls);
        static::assertSame('value1', $store->get('flexible:key'));
        static::assertNotNull($store->get('illuminate:cache:flexible:created:flexible:key'));

        // Fresh: callback is not called.
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        static::assertSame('value1', $store->flexible('flexible:key', [60, 120], $callback));
        static::assertSame(1, $calls);
    }

    #[DataProvider('storesProvider')]
    public function testFlexibleRefreshesStaleValue(string $storeName): void
    {
        $store = $this->store($storeName);
        Carbon::setTestNow(Carbon::now());

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'value' . $calls;
        };

        static::assertSame('value1', $store->flexible('flexible:stale', [10, 3600], $callback));
        $created = $store->get('illuminate:cache:flexible:created:flexible:stale');

        // Stale: old value is returned, refresh runs (immediately because of withoutDefer()).
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        static::assertSame('value1', $store->flexible('flexible:stale', [10, 3600], $callback));
        static::assertSame(2, $calls);
        static::assertSame('value2', $store->get('flexible:stale'));
        static::assertGreaterThan($created, $store->get('illuminate:cache:flexible:created:flexible:stale'));

        // Refreshed value is fresh again.
        static::assertSame('value2', $store->flexible('flexible:stale', [10, 3600], $callback));
        static::assertSame(2, $calls);

        // The refresh lock must be released.
        static::assertTrue($store->lock('illuminate:cache:flexible:lock:flexible:stale', 10)->get());
    }

    #[DataProvider('storesProvider')]
    public function testFlexibleRecomputesWhenCreatedTimestampIsMissing(string $storeName): void
    {
        $store = $this->store($storeName);

        $store->put('flexible:partial', 'old', 3600);
        static::assertSame('new', $store->flexible('flexible:partial', [60, 120], fn () => 'new'));
        static::assertSame('new', $store->get('flexible:partial'));
    }

    #[DataProvider('storesProvider')]
    public function testFlexibleWithTags(string $storeName): void
    {
        $store = $this->store($storeName);
        Carbon::setTestNow(Carbon::now());

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'value' . $calls;
        };

        $tagged = $store->tags(['tag1', 'tag2']);
        static::assertSame('value1', $tagged->flexible('flexible:tagged', [10, 3600], $callback));
        static::assertSame('value1', $store->get('flexible:tagged'));

        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        static::assertSame('value1', $store->tags(['tag1'])->flexible('flexible:tagged', [10, 3600], $callback));
        static::assertSame('value2', $store->get('flexible:tagged'));

        // Refreshed items keep tags.
        $store->tags(['tag1'])->flush();
        static::assertNull($store->get('flexible:tagged'));
        static::assertNull($store->get('illuminate:cache:flexible:created:flexible:tagged'));

        static::assertSame('value3', $tagged->flexible('flexible:tagged', [10, 3600], $callback));
    }
}
