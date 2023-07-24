<?php

declare(strict_types=1);

namespace Tests\Feature;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Stringable;
use Tests\TestCase;

class CacheTagsTest extends TestCase
{
    private function getCache(): CacheManager
    {
        return $this->app['cache'];
    }

    protected function tearDown(): void
    {
        /** @var AlternativeRedisCacheStore|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');
        /** @var AlternativeFileCacheStore|Repository $hierarchialFileStore */
        $hierarchialFileStore = $this->getCache()->store('hierarchial_file');
//        /** @var AlternativeMemcachedCacheStore|Repository $memcachedStore */
//        $memcachedStore = $this->getCache()->store('memcached');

        $redisStore->flush();
        $fileStore->flush();
        $hierarchialFileStore->flush();
//        $memcachedStore->flush();

        parent::tearDown();
    }

    /**
     * Test that tags get treated properly by the various stores.
     *
     * @test
     * @dataProvider tagDataProvider
     *
     * @param string                           $store The store to test.
     * @param array<integer,string|Stringable> $tags  The tags to use.
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testStringableCacheTags(string $store, array $tags): void
    {
        /** @var AlternativeFileCacheStore|AlternativeRedisCacheStore|Repository $store */
        $store = $this->getCache()->store($store);
        $store->flush();

        $value = mt_rand();

        // store a key using the tags
        $store->tags($tags)->put('key', $value);
        self::assertSame($value, $store->get('key'));
        self::assertSame($value, $store->tags($tags)->get('key'));

        // forget the key using the tags
        $store->tags($tags)->forget('key');
        self::assertNull($store->get('key'));
        self::assertNull($store->tags($tags)->get('key'));
    }

    public static function tagDataProvider(): array
    {
        $possibleStores = [
            'redis',
            'file',
            'hierarchial_file',
//            'memcached',
        ];

        $possibleTags = [
            ['tag1'],
            ['tag1', 'tag2'],
            [new Tag('tag1')],
            [new Tag('tag1'), new Tag('tag2')],
            ['tag1', new Tag('tag2')],
        ];

        $return = [];
        foreach ($possibleStores as $store) {
            foreach ($possibleTags as $tags) {
                $return[] = ['store' => $store, 'tags' => $tags];
            }
        }

        return $return;
    }
}

/**
 * A class that implements the Stringable interface, to be used as cache tags.
 */
class Tag implements Stringable
{
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
