<?php

declare(strict_types=1);

namespace Tests\Feature;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeFileCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStore;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStore;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStoreWithLocks;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Predis\Collection\Iterator\Keyspace;
use Tests\TestCase;

class AlternativeLaravelCacheTest extends TestCase
{
    /*
     * Config file cache.php:
        [
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => storage_path('framework/cache'),
                ],

                'hierarchial_file' => [
                    'driver' => 'hierarchial_file',
                    'path' => storage_path('framework/cache'),
                ],

                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'cache',
                ],
    
                'memcached' => [
                    'driver' => 'memcached',
                    'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
                    'sasl' => [
                        env('MEMCACHED_USERNAME'),
                        env('MEMCACHED_PASSWORD'),
                    ],
                    'options' => [
                        // Memcached::OPT_CONNECT_TIMEOUT => 2000,
                    ],
                    'servers' => [
                        [
                            'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                            'port' => env('MEMCACHED_PORT', 11211),
                            'weight' => 100,
                        ],
                    ],
                ],
            ]
            'prefix' => 'laravel/testcache'
        ]
     */

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

        $redisStore->flush();
        $fileStore->flush();
        $hierarchialFileStore->flush();

        parent::tearDown();
    }

    public function testNormalCache(): void
    {
        /** @var AlternativeRedisCacheStore|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');

        $redisStore->flush();
        $fileStore->flush();

        $key1 = 'key1';
        $redisStore->put($key1, 'value1', 3600);
        self::assertEquals('value1', $redisStore->get($key1));
        $fileStore->put($key1, 'value2', 3600);
        self::assertEquals('value2', $fileStore->get($key1));

        $key2 = 'key1|subkey/sskey\\ssskey';
        $redisStore->put($key2, 'value1', 3600);
        self::assertEquals('value1', $redisStore->get($key2));
        $fileStore->put($key2, 'value2', 3600);
        self::assertEquals('value2', $fileStore->get($key2));
    }

    public function testMemcachedCache(): void
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        if (class_exists('\Memcached')) {
            /** @var AlternativeMemcachedCacheStore|Repository $memcachedStore */
            $memcachedStore = $this->getCache()
                ->store('memcached');

            $memcachedStore->flush();

            $key = 'key1|subkey/sskey\\ssskey';
            $memcachedStore->put($key, 'value3', 3600);
            self::assertEquals('value3', $memcachedStore->get($key));
        } else {
            static::assertTrue(true);
        }
    }

    public function testHierarchialFileCache(): void
    {
        /** @var AlternativeFileCacheStore|Repository $hierarchialFileStore */
        $hierarchialFileStore = $this->getCache()->store('hierarchial_file');
        $hierarchialFileStore->flush();
        $key1 = 'key1|subkey1';
        $hierarchialFileStore->put($key1, 'value1', 3600);
        self::assertEquals('value1', $hierarchialFileStore->get($key1));
        $key2 = 'key1|subkey2';
        $hierarchialFileStore->put($key2, 'value2', 3600);
        self::assertEquals('value2', $hierarchialFileStore->get($key2));
        $key3 = 'key2|subkey1';
        $hierarchialFileStore->put($key3, 'value3', 3600);
        self::assertEquals('value3', $hierarchialFileStore->get($key3));
        $hierarchialFileStore->forget('key1');
        self::assertNull($hierarchialFileStore->get($key1));
        self::assertNull($hierarchialFileStore->get($key2));
        self::assertEquals('value3', $hierarchialFileStore->get($key3));
    }

    public function testTaggedCache(): void
    {
        /** @var AlternativeRedisCacheStore|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');
        /** @var AlternativeFileCacheStore|Repository $hierarchialFileStore */
        $hierarchialFileStore = $this->getCache()->store('hierarchial_file');

        $redisStore->flush();
        $fileStore->flush();
        $hierarchialFileStore->flush();

        $key1 = 'key1|subkey/sskey\\ssskey1';
        $key2 = 'key1|subkey/sskey\\ssskey2';

        $redisStore->tags(['tag1', 'tag2'])->put($key1, 'value1', 3600);
        self::assertEquals('value1', $redisStore->get($key1));
        $redisStore->tags(['tag3'])->put($key2, 'value11', 3600);
        self::assertEquals('value11', $redisStore->get($key2));

        $fileStore->tags(['tag1', 'tag2'])->put($key1, 'value2', 3600);
        self::assertEquals('value2', $fileStore->get($key1));
        $fileStore->tags(['tag3'])->put($key2, 'value22', 3600);
        self::assertEquals('value22', $fileStore->get($key2));

        $hierarchialFileStore->tags(['tag1', 'tag2'])->put($key1, 'value3', 3600);
        self::assertEquals('value3', $hierarchialFileStore->get($key1));
        $hierarchialFileStore->tags(['tag3'])->put($key2, 'value33', 3600);
        self::assertEquals('value33', $hierarchialFileStore->get($key2));

        $redisStore->tags(['tag1'])->flush();
        self::assertEquals('value11', $redisStore->get($key2));
        self::assertNull($redisStore->get($key1));

        $fileStore->tags(['tag1'])->flush();
        self::assertEquals('value22', $fileStore->get($key2));
        self::assertNull($fileStore->get($key1));

        $hierarchialFileStore->tags(['tag1'])->flush();
        self::assertEquals('value33', $hierarchialFileStore->get($key2));
        self::assertNull($hierarchialFileStore->get($key1));
    }

    public function testTaggedCacheFlushForFiles()
    {
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');

        $fileStore->flush();

        $key1 = 'key1';
        $key2 = 'key2';

        $fileStore->tags(['tag1', 'tag2'])->put($key1, 'value2', 3600);
        self::assertEquals('value2', $fileStore->get($key1));
        $fileStore->tags(['tag3'])->put($key2, 'value22', 3600);
        self::assertEquals('value22', $fileStore->get($key2));

        $fileStore->tags(['tag1'])->flush();
        self::assertEquals('value22', $fileStore->get($key2));
        self::assertNull($fileStore->get($key1));
    }

    public function testLocks(): void
    {
        /** @var AlternativeRedisCacheStoreWithLocks|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStoreWithLocks|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');
        /** @var AlternativeFileCacheStoreWithLocks|Repository $hierarchialFileStore */
        $hierarchialFileStore = $this->getCache()->store('hierarchial_file');

        $redisStore->flush();
        $fileStore->flush();
        $hierarchialFileStore->flush();

        // redis locks
        static::assertTrue(method_exists($redisStore->getStore(), 'lock'));
        static::assertTrue(method_exists($redisStore->getStore(), 'restoreLock'));
        static::assertTrue(method_exists($redisStore->getStore(), 'setLockConnection'));
        static::assertTrue(method_exists($redisStore->getStore(), 'lockConnection'));
        $lock = $redisStore->lock('test', 10, 'tests');
        $redisStore->restoreLock('test', 'tests');
        $lock->release();

        // file locks
        static::assertTrue(method_exists($fileStore->getStore(), 'lock'));
        static::assertTrue(method_exists($fileStore->getStore(), 'restoreLock'));
        $lock = $fileStore->lock('test', 10, 'tests');
        $fileStore->restoreLock('test', 'tests');
        $lock->release();

        // hierarchial file locks
        static::assertTrue(method_exists($hierarchialFileStore->getStore(), 'lock'));
        static::assertTrue(method_exists($hierarchialFileStore->getStore(), 'restoreLock'));
        $lock = $hierarchialFileStore->lock('test', 10, 'tests');
        $hierarchialFileStore->restoreLock('test', 'tests');
        $lock->release();
    }

    public function testHierarchialCacheKeysInRedis(): void
    {
        /** @var AlternativeRedisCacheStoreWithLocks|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');

        $redisStore->flush();

        $keyPiped = 'key1|subkey1|sskey1|ssskey1';
        $keySlashed = 'key2/subkey2/sskey2/ssskey2';
        $flushPipedKeyUsingPipe = 'key1|subkey1';
        $flushPipedKeyUsingSlash = 'key1/subkey1';
        $flushSlashedKeyUsingPipe = 'key2|subkey2';
        $flushSlashedKeyUsingSlash = 'key2/subkey2';

        $redisStore->put($keyPiped, 'value1', 3600);
        self::assertEquals('value1', $redisStore->get($keyPiped));
        $redisStore->put($keySlashed, 'value11', 3600);
        self::assertEquals('value11', $redisStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipedKeyUsingPipe

        $redisStore->forget($flushPipedKeyUsingPipe);

        self::assertNull($redisStore->get($keyPiped));
        self::assertEquals('value11', $redisStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipiedKeyUsingSlash

        $redisStore->put($keyPiped, 'value1', 3600);
        $redisStore->put($keySlashed, 'value11', 3600);

        $redisStore->forget($flushPipedKeyUsingSlash);

        self::assertEquals('value1', $redisStore->get($keyPiped));
        self::assertEquals('value11', $redisStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingPipe

        $redisStore->put($keyPiped, 'value1', 3600);
        $redisStore->put($keySlashed, 'value11', 3600);

        $redisStore->forget($flushSlashedKeyUsingPipe);

        self::assertEquals('value1', $redisStore->get($keyPiped));
        self::assertEquals('value11', $redisStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingSlash

        $redisStore->put($keyPiped, 'value1', 3600);
        $redisStore->put($keySlashed, 'value11', 3600);

        $redisStore->forget($flushSlashedKeyUsingSlash);

        self::assertEquals('value1', $redisStore->get($keyPiped));
        self::assertEquals('value11', $redisStore->get($keySlashed));

        $redisStore->flush();
    }

    public function testHierarchialCacheKeysInFileStore(): void
    {
        /** @var AlternativeFileCacheStoreWithLocks|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');

        $fileStore->flush();

        $keyPiped = 'key1|subkey1|sskey1|ssskey1';
        $keySlashed = 'key2/subkey2/sskey2/ssskey2';
        $flushPipedKeyUsingPipe = 'key1|subkey1';
        $flushPipedKeyUsingSlash = 'key1/subkey1';
        $flushSlashedKeyUsingPipe = 'key2|subkey2';
        $flushSlashedKeyUsingSlash = 'key2/subkey2';

        $fileStore->put($keyPiped, 'value2', 3600);
        self::assertEquals('value2', $fileStore->get($keyPiped));
        $fileStore->put($keySlashed, 'value22', 3600);
        self::assertEquals('value22', $fileStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipedKeyUsingPipe

        $fileStore->forget($flushPipedKeyUsingPipe);

        self::assertEquals('value2', $fileStore->get($keyPiped));
        self::assertEquals('value22', $fileStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipiedKeyUsingSlash

        $fileStore->put($keyPiped, 'value2', 3600);
        $fileStore->put($keySlashed, 'value22', 3600);

        $fileStore->forget($flushPipedKeyUsingSlash);

        self::assertEquals('value2', $fileStore->get($keyPiped));
        self::assertEquals('value22', $fileStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingPipe

        $fileStore->put($keyPiped, 'value2', 3600);
        $fileStore->put($keySlashed, 'value22', 3600);

        $fileStore->forget($flushSlashedKeyUsingPipe);

        self::assertEquals('value2', $fileStore->get($keyPiped));
        self::assertEquals('value22', $fileStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingSlash

        $fileStore->put($keyPiped, 'value2', 3600);
        $fileStore->put($keySlashed, 'value22', 3600);

        $fileStore->forget($flushSlashedKeyUsingSlash);

        self::assertEquals('value2', $fileStore->get($keyPiped));
        self::assertEquals('value22', $fileStore->get($keySlashed));

        $fileStore->flush();
    }

    public function testHierarchialCacheKeysInHierarchialFileStore(): void
    {
        /** @var AlternativeFileCacheStoreWithLocks|Repository $hierarchialFileStore */
        $hierarchialFileStore = $this->getCache()->store('hierarchial_file');

        $hierarchialFileStore->flush();

        $keyPiped = 'key1|subkey1|sskey1|ssskey1';
        $keySlashed = 'key2/subkey2/sskey2/ssskey2';
        $flushPipedKeyUsingPipe = 'key1|subkey1';
        $flushPipedKeyUsingSlash = 'key1/subkey1';
        $flushSlashedKeyUsingPipe = 'key2|subkey2';
        $flushSlashedKeyUsingSlash = 'key2/subkey2';

        $hierarchialFileStore->put($keyPiped, 'value3', 3600);
        self::assertEquals('value3', $hierarchialFileStore->get($keyPiped));
        $hierarchialFileStore->put($keySlashed, 'value33', 3600);
        self::assertEquals('value33', $hierarchialFileStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipedKeyUsingPipe

        $hierarchialFileStore->forget($flushPipedKeyUsingPipe);

        self::assertNull($hierarchialFileStore->get($keyPiped));
        self::assertEquals('value33', $hierarchialFileStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipiedKeyUsingSlash

        $hierarchialFileStore->put($keyPiped, 'value3', 3600);
        $hierarchialFileStore->put($keySlashed, 'value33', 3600);

        $hierarchialFileStore->forget($flushPipedKeyUsingSlash);

        self::assertNull($hierarchialFileStore->get($keyPiped));
        self::assertEquals('value33', $hierarchialFileStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingPipe

        $hierarchialFileStore->put($keyPiped, 'value3', 3600);
        $hierarchialFileStore->put($keySlashed, 'value33', 3600);

        $hierarchialFileStore->forget($flushSlashedKeyUsingPipe);

        self::assertEquals('value3', $hierarchialFileStore->get($keyPiped));
        self::assertNull($hierarchialFileStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingSlash

        $hierarchialFileStore->put($keyPiped, 'value3', 3600);
        $hierarchialFileStore->put($keySlashed, 'value33', 3600);

        $hierarchialFileStore->forget($flushSlashedKeyUsingSlash);

        self::assertEquals('value3', $hierarchialFileStore->get($keyPiped));
        self::assertNull($hierarchialFileStore->get($keySlashed));

        $hierarchialFileStore->flush();
    }

    public function testMemcachedLocks(): void
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        if (class_exists('\Memcached')) {
            /** @var AlternativeMemcachedCacheStoreWithLocks|Repository $memcachedStore */
            $memcachedStore = $this->getCache()->store('memcached');

            $memcachedStore->flush();

            static::assertTrue(method_exists($memcachedStore->getStore(), 'lock'));
            static::assertTrue(method_exists($memcachedStore->getStore(), 'restoreLock'));
            $lock = $memcachedStore->lock('test', 10, 'tests');
            $memcachedStore->restoreLock('test', 'tests');
            $lock->release();
        } else {
            static::assertTrue(true);
        }
    }

    public function testRedisKeysCreation(): void
    {
        /** @var AlternativeRedisCacheStoreWithLocks|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        $redisStore->flush();
        $this->assertCount(0, $this->getRedisKeys());

        $key1 = 'key1';
        $redisStore->put($key1, 'value1', 3600);
        self::assertEquals('value1', $redisStore->get($key1));
        $keySha1 = sha1(str_replace('|', '!!!', 'root' . $redisStore->itemKey($key1) . '|'));
        //something like 'alternative_laravel_cache_test_database_13737195f3252a099b3ced6a237d2a128ccdac61'
        $this->assertEquals([config('database.redis.options.prefix') . $keySha1], $this->getRedisKeys());
    }

    private function getRedisKeys(): array
    {
        /** @var AlternativeRedisCacheStoreWithLocks|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        $client = $redisStore->getConnection();
        if ($client instanceof \Redis) {
            $keys = [];
            $it = null;
            do {
                /** @noinspection PhpParamsInspection */
                $newKeys = $client->scan($it, '*', 1000);
                if (!empty($newKeys)) {
                    $keys += $newKeys;
                }
            } while ($it > 0 && !empty($newKeys));
        } else {
            $iterator = new Keyspace($client, null, 1000);
            $keys = [];
            foreach ($iterator as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

}
