<?php

declare(strict_types=1);

namespace Tests\Feature;

use AlternativeLaravelCache\Store\AlternativeArrayCacheStore;
use AlternativeLaravelCache\Store\AlternativeArrayCacheStoreWithLocks;
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
use Tests\TestClass\StringableTestClassPhp7;
use Tests\TestClass\StringableTestClassPhp8;

class AlternativeLaravelCacheTest extends TestCase
{
    /*
     * Config file cache.php:
        [
            'stores' => [
                'array' => [
                    'driver' => 'array',
                ],

                'file' => [
                    'driver' => 'file',
                    'path' => storage_path('framework/cache'),
                ],

                'hierarchical_file' => [
                    'driver' => 'hierarchical_file',
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
        /** @var AlternativeFileCacheStore|Repository $hierarchicalFileStore */
        $hierarchicalFileStore = $this->getCache()->store('hierarchical_file');
        /** @var AlternativeArrayCacheStore|Repository $arrayStore */
        $arrayStore = $this->getCache()->store('array');

        $redisStore->flush();
        $fileStore->flush();
        $hierarchicalFileStore->flush();
        $arrayStore->flush();

        if (class_exists('\Memcached')) {
            /** @var AlternativeMemcachedCacheStore|Repository $memcachedStore */
            $memcachedStore = $this->getCache()
                ->store('memcached');
            $memcachedStore->flush();
        }

        parent::tearDown();
    }

    public function testNormalCache(): void
    {
        /** @var AlternativeRedisCacheStore|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');
        /** @var AlternativeArrayCacheStore|Repository $arrayStore */
        $arrayStore = $this->getCache()->store('array');

        $redisStore->flush();
        $fileStore->flush();
        $arrayStore->flush();

        $key1 = 'key1';
        $redisStore->put($key1, 'value1', 3600);
        static::assertEquals('value1', $redisStore->get($key1));
        $fileStore->put($key1, 'value2', 3600);
        static::assertEquals('value2', $fileStore->get($key1));
        $arrayStore->put($key1, 'value3', 3600);
        static::assertEquals('value3', $arrayStore->get($key1));

        $key2 = 'key1|subkey/sskey\\ssskey';
        $redisStore->put($key2, 'value1', 3600);
        static::assertEquals('value1', $redisStore->get($key2));
        $fileStore->put($key2, 'value2', 3600);
        static::assertEquals('value2', $fileStore->get($key2));
        $arrayStore->put($key2, 'value3', 3600);
        static::assertEquals('value3', $arrayStore->get($key2));

        $key3 = new StringableTestClassPhp7('key3');
        $redisStore->put($key3, 'value1', 3600);
        static::assertEquals('value1', $redisStore->get($key3));
        $fileStore->put($key3, 'value2', 3600);
        static::assertEquals('value2', $fileStore->get($key3));
        $arrayStore->put($key3, 'value3', 3600);
        static::assertEquals('value3', $arrayStore->get($key3));

        if (PHP_VERSION_ID >= 80000) {
            $key4 = new StringableTestClassPhp8('key4');
            $redisStore->put($key4, 'value1', 3600);
            static::assertEquals('value1', $redisStore->get($key4));
            $fileStore->put($key4, 'value2', 3600);
            static::assertEquals('value2', $fileStore->get($key4));
            $arrayStore->put($key4, 'value3', 3600);
            static::assertEquals('value3', $arrayStore->get($key4));
        }
    }

    public function testMemcachedCache(): void
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        if (class_exists('\Memcached')) {
            /** @var AlternativeMemcachedCacheStore|Repository $memcachedStore */
            $memcachedStore = $this->getCache()
                ->store('memcached');

            $memcachedStore->flush();

            $key1 = 'key1';
            $memcachedStore->put($key1, 'value1', 3600);
            static::assertEquals('value1', $memcachedStore->get($key1));

            $key2 = 'key1|subkey/sskey\\ssskey';
            $memcachedStore->put($key2, 'value3', 3600);
            static::assertEquals('value3', $memcachedStore->get($key2));

            $key3 = new StringableTestClassPhp7('key2');
            $memcachedStore->put($key3, 'value1', 3600);
            static::assertEquals('value1', $memcachedStore->get($key3));

            if (PHP_VERSION_ID >= 80000) {
                $key4 = new StringableTestClassPhp8('key3');
                $memcachedStore->put($key4, 'value1', 3600);
                static::assertEquals('value1', $memcachedStore->get($key4));
            }
        } else {
            static::assertTrue(true);
        }
    }

    public function testHierarchicalFileCache(): void
    {
        /** @var AlternativeFileCacheStore|Repository $hierarchicalFileStore */
        $hierarchicalFileStore = $this->getCache()->store('hierarchical_file');
        $hierarchicalFileStore->flush();
        $key1 = 'key1|subkey1';
        $hierarchicalFileStore->put($key1, 'value1', 3600);
        static::assertEquals('value1', $hierarchicalFileStore->get($key1));
        $key2 = 'key1|subkey2';
        $hierarchicalFileStore->put($key2, 'value2', 3600);
        static::assertEquals('value2', $hierarchicalFileStore->get($key2));
        $key3 = 'key2|subkey1';
        $hierarchicalFileStore->put($key3, 'value3', 3600);
        static::assertEquals('value3', $hierarchicalFileStore->get($key3));
        $hierarchicalFileStore->forget('key1');
        static::assertNull($hierarchicalFileStore->get($key1));
        static::assertNull($hierarchicalFileStore->get($key2));
        static::assertEquals('value3', $hierarchicalFileStore->get($key3));

        $key4 = new StringableTestClassPhp7('key4|subkey1');
        $hierarchicalFileStore->put($key4, 'value1', 3600);
        static::assertEquals('value1', $hierarchicalFileStore->get($key4));

        if (PHP_VERSION_ID >= 80000) {
            $key5 = new StringableTestClassPhp8('key5|subkey1');
            $hierarchicalFileStore->put($key5, 'value1', 3600);
            static::assertEquals('value1', $hierarchicalFileStore->get($key5));
        }
    }

    public function testTaggedCache(): void
    {
        /** @var AlternativeRedisCacheStore|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');
        /** @var AlternativeFileCacheStore|Repository $hierarchicalFileStore */
        $hierarchicalFileStore = $this->getCache()->store('hierarchical_file');
        /** @var AlternativeArrayCacheStore|Repository $arrayStore */
        $arrayStore = $this->getCache()->store('array');

        $redisStore->flush();
        $fileStore->flush();
        $hierarchicalFileStore->flush();
        $arrayStore->flush();

        $key1 = 'key1|subkey/sskey\\ssskey1';
        $key2 = 'key1|subkey/sskey\\ssskey2';

        $redisStore->tags(['tag1', 'tag2'])->put($key1, 'value1', 3600);
        static::assertEquals('value1', $redisStore->get($key1));
        $redisStore->tags(['tag3'])->put($key2, 'value11', 3600);
        static::assertEquals('value11', $redisStore->get($key2));

        $fileStore->tags(['tag1', 'tag2'])->put($key1, 'value2', 3600);
        static::assertEquals('value2', $fileStore->get($key1));
        $fileStore->tags(['tag3'])->put($key2, 'value22', 3600);
        static::assertEquals('value22', $fileStore->get($key2));

        $hierarchicalFileStore->tags(['tag1', 'tag2'])->put($key1, 'value3', 3600);
        static::assertEquals('value3', $hierarchicalFileStore->get($key1));
        $hierarchicalFileStore->tags(['tag3'])->put($key2, 'value33', 3600);
        static::assertEquals('value33', $hierarchicalFileStore->get($key2));

        $arrayStore->tags(['tag1', 'tag2'])->put($key1, 'value4', 3600);
        static::assertEquals('value4', $arrayStore->get($key1));
        $arrayStore->tags(['tag3'])->put($key2, 'value44', 3600);
        static::assertEquals('value44', $arrayStore->get($key2));

        $redisStore->tags(['tag1'])->flush();
        static::assertEquals('value11', $redisStore->get($key2));
        static::assertNull($redisStore->get($key1));

        $fileStore->tags(['tag1'])->flush();
        static::assertEquals('value22', $fileStore->get($key2));
        static::assertNull($fileStore->get($key1));

        $hierarchicalFileStore->tags(['tag1'])->flush();
        static::assertEquals('value33', $hierarchicalFileStore->get($key2));
        static::assertNull($hierarchicalFileStore->get($key1));

        $arrayStore->tags(['tag1'])->flush();
        static::assertEquals('value44', $arrayStore->get($key2));
        static::assertNull($arrayStore->get($key1));

        // stringable tags

        $redisStore->flush();
        $fileStore->flush();
        $hierarchicalFileStore->flush();
        $arrayStore->flush();

        $stringableTag1 = new StringableTestClassPhp7('stringable_tag1');

        $redisStore->tags($stringableTag1)->put($key1, 'value1', 3600);
        static::assertEquals('value1', $redisStore->get($key1));
        $redisStore->tags($stringableTag1)->flush();
        static::assertNull($redisStore->get($key1));

        $fileStore->tags($stringableTag1)->put($key1, 'value1', 3600);
        static::assertEquals('value1', $fileStore->get($key1));
        $fileStore->tags($stringableTag1)->flush();
        static::assertNull($fileStore->get($key1));

        $hierarchicalFileStore->tags($stringableTag1)->put($key1, 'value1', 3600);
        static::assertEquals('value1', $hierarchicalFileStore->get($key1));
        $hierarchicalFileStore->tags($stringableTag1)->flush();
        static::assertNull($hierarchicalFileStore->get($key1));

        $arrayStore->tags($stringableTag1)->put($key1, 'value1', 3600);
        static::assertEquals('value1', $arrayStore->get($key1));
        $arrayStore->tags($stringableTag1)->flush();
        static::assertNull($arrayStore->get($key1));

        if (PHP_VERSION_ID >= 80000) {
            $stringableTag2 = new StringableTestClassPhp8('stringable_tag2');

            $redisStore->tags($stringableTag2)->put($key1, 'value1', 3600);
            static::assertEquals('value1', $redisStore->get($key1));
            $redisStore->tags($stringableTag2)->flush();
            static::assertNull($redisStore->get($key1));

            $fileStore->tags($stringableTag2)->put($key1, 'value1', 3600);
            static::assertEquals('value1', $fileStore->get($key1));
            $fileStore->tags($stringableTag2)->flush();
            static::assertNull($fileStore->get($key1));

            $hierarchicalFileStore->tags($stringableTag2)->put($key1, 'value1', 3600);
            static::assertEquals('value1', $hierarchicalFileStore->get($key1));
            $hierarchicalFileStore->tags($stringableTag2)->flush();
            static::assertNull($hierarchicalFileStore->get($key1));

            $arrayStore->tags($stringableTag2)->put($key1, 'value1', 3600);
            static::assertEquals('value1', $arrayStore->get($key1));
            $arrayStore->tags($stringableTag2)->flush();
            static::assertNull($arrayStore->get($key1));
        }
    }

    public function testTaggedCacheFlushForFiles()
    {
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');

        $fileStore->flush();

        $key1 = 'key1';
        $key2 = 'key2';

        $fileStore->tags(['tag1', 'tag2'])->put($key1, 'value2', 3600);
        static::assertEquals('value2', $fileStore->get($key1));
        $fileStore->tags(['tag3'])->put($key2, 'value22', 3600);
        static::assertEquals('value22', $fileStore->get($key2));

        $fileStore->tags(['tag1'])->flush();
        static::assertEquals('value22', $fileStore->get($key2));
        static::assertNull($fileStore->get($key1));
    }

    public function testLocks(): void
    {
        /** @var AlternativeRedisCacheStoreWithLocks|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStoreWithLocks|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');
        /** @var AlternativeFileCacheStoreWithLocks|Repository $hierarchicalFileStore */
        $hierarchicalFileStore = $this->getCache()->store('hierarchical_file');
        /** @var AlternativeArrayCacheStoreWithLocks|Repository $arrayStore */
        $arrayStore = $this->getCache()->store('array');

        $redisStore->flush();
        $fileStore->flush();
        $hierarchicalFileStore->flush();
        $arrayStore->flush();

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

        // hierarchical file locks
        static::assertTrue(method_exists($hierarchicalFileStore->getStore(), 'lock'));
        static::assertTrue(method_exists($hierarchicalFileStore->getStore(), 'restoreLock'));
        $lock = $hierarchicalFileStore->lock('test', 10, 'tests');
        $hierarchicalFileStore->restoreLock('test', 'tests');
        $lock->release();

        // array locks
        static::assertTrue(method_exists($arrayStore->getStore(), 'lock'));
        static::assertTrue(method_exists($arrayStore->getStore(), 'restoreLock'));
        $lock = $arrayStore->lock('test', 10, 'tests');
        $arrayStore->restoreLock('test', 'tests');
        $lock->release();
    }

    public function testHierarchicalCacheKeysInRedis(): void
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
        static::assertEquals('value1', $redisStore->get($keyPiped));
        $redisStore->put($keySlashed, 'value11', 3600);
        static::assertEquals('value11', $redisStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipedKeyUsingPipe

        $redisStore->forget($flushPipedKeyUsingPipe);

        static::assertNull($redisStore->get($keyPiped));
        static::assertEquals('value11', $redisStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipiedKeyUsingSlash

        $redisStore->put($keyPiped, 'value1', 3600);
        $redisStore->put($keySlashed, 'value11', 3600);

        $redisStore->forget($flushPipedKeyUsingSlash);

        static::assertEquals('value1', $redisStore->get($keyPiped));
        static::assertEquals('value11', $redisStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingPipe

        $redisStore->put($keyPiped, 'value1', 3600);
        $redisStore->put($keySlashed, 'value11', 3600);

        $redisStore->forget($flushSlashedKeyUsingPipe);

        static::assertEquals('value1', $redisStore->get($keyPiped));
        static::assertEquals('value11', $redisStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingSlash

        $redisStore->put($keyPiped, 'value1', 3600);
        $redisStore->put($keySlashed, 'value11', 3600);

        $redisStore->forget($flushSlashedKeyUsingSlash);

        static::assertEquals('value1', $redisStore->get($keyPiped));
        static::assertEquals('value11', $redisStore->get($keySlashed));

        // stringable key

        $stringableKey1 = new StringableTestClassPhp7('stringable1|subkey1|sskey1|ssskey1');
        $redisStore->put($stringableKey1, 'value1', 3600);
        static::assertEquals('value1', $redisStore->get($stringableKey1));
        $redisStore->forget('stringable1|subkey1');
        static::assertNull($redisStore->get($stringableKey1));

        if (PHP_VERSION_ID >= 80000) {
            $stringableKey2 = new StringableTestClassPhp8('stringable2|subkey1|sskey1|ssskey1');
            $redisStore->put($stringableKey2, 'value1', 3600);
            static::assertEquals('value1', $redisStore->get($stringableKey2));
            $redisStore->forget('stringable2|subkey1');
            static::assertNull($redisStore->get($stringableKey2));
        }

        $redisStore->flush();
    }

    public function testHierarchicalCacheKeysInFileStore(): void
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
        static::assertEquals('value2', $fileStore->get($keyPiped));
        $fileStore->put($keySlashed, 'value22', 3600);
        static::assertEquals('value22', $fileStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipedKeyUsingPipe

        $fileStore->forget($flushPipedKeyUsingPipe);

        static::assertEquals('value2', $fileStore->get($keyPiped));
        static::assertEquals('value22', $fileStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipiedKeyUsingSlash

        $fileStore->put($keyPiped, 'value2', 3600);
        $fileStore->put($keySlashed, 'value22', 3600);

        $fileStore->forget($flushPipedKeyUsingSlash);

        static::assertEquals('value2', $fileStore->get($keyPiped));
        static::assertEquals('value22', $fileStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingPipe

        $fileStore->put($keyPiped, 'value2', 3600);
        $fileStore->put($keySlashed, 'value22', 3600);

        $fileStore->forget($flushSlashedKeyUsingPipe);

        static::assertEquals('value2', $fileStore->get($keyPiped));
        static::assertEquals('value22', $fileStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingSlash

        $fileStore->put($keyPiped, 'value2', 3600);
        $fileStore->put($keySlashed, 'value22', 3600);

        $fileStore->forget($flushSlashedKeyUsingSlash);

        static::assertEquals('value2', $fileStore->get($keyPiped));
        static::assertEquals('value22', $fileStore->get($keySlashed));

        // stringable key

        $stringableKey1 = new StringableTestClassPhp7('stringable1|subkey1|sskey1|ssskey1');
        $fileStore->put($stringableKey1, 'value1', 3600);
        static::assertEquals('value1', $fileStore->get($stringableKey1));
        $fileStore->forget('stringable1|subkey1');
        static::assertEquals('value1', $fileStore->get($stringableKey1));

        if (PHP_VERSION_ID >= 80000) {
            $stringableKey2 = new StringableTestClassPhp8('stringable2|subkey1|sskey1|ssskey1');
            $fileStore->put($stringableKey2, 'value1', 3600);
            static::assertEquals('value1', $fileStore->get($stringableKey2));
            $fileStore->forget('stringable2|subkey1');
            static::assertEquals('value1', $fileStore->get($stringableKey2));
        }

        $fileStore->flush();
    }

    public function testHierarchicalCacheKeysInHierarchicalFileStore(): void
    {
        /** @var AlternativeFileCacheStoreWithLocks|Repository $hierarchicalFileStore */
        $hierarchicalFileStore = $this->getCache()->store('hierarchical_file');

        $hierarchicalFileStore->flush();

        $keyPiped = 'key1|subkey1|sskey1|ssskey1';
        $keySlashed = 'key2/subkey2/sskey2/ssskey2';
        $flushPipedKeyUsingPipe = 'key1|subkey1';
        $flushPipedKeyUsingSlash = 'key1/subkey1';
        $flushSlashedKeyUsingPipe = 'key2|subkey2';
        $flushSlashedKeyUsingSlash = 'key2/subkey2';

        $hierarchicalFileStore->put($keyPiped, 'value3', 3600);
        static::assertEquals('value3', $hierarchicalFileStore->get($keyPiped));
        $hierarchicalFileStore->put($keySlashed, 'value33', 3600);
        static::assertEquals('value33', $hierarchicalFileStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipedKeyUsingPipe

        $hierarchicalFileStore->forget($flushPipedKeyUsingPipe);

        static::assertNull($hierarchicalFileStore->get($keyPiped));
        static::assertEquals('value33', $hierarchicalFileStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipiedKeyUsingSlash

        $hierarchicalFileStore->put($keyPiped, 'value3', 3600);
        $hierarchicalFileStore->put($keySlashed, 'value33', 3600);

        $hierarchicalFileStore->forget($flushPipedKeyUsingSlash);

        static::assertNull($hierarchicalFileStore->get($keyPiped));
        static::assertEquals('value33', $hierarchicalFileStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingPipe

        $hierarchicalFileStore->put($keyPiped, 'value3', 3600);
        $hierarchicalFileStore->put($keySlashed, 'value33', 3600);

        $hierarchicalFileStore->forget($flushSlashedKeyUsingPipe);

        static::assertEquals('value3', $hierarchicalFileStore->get($keyPiped));
        static::assertNull($hierarchicalFileStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingSlash

        $hierarchicalFileStore->put($keyPiped, 'value3', 3600);
        $hierarchicalFileStore->put($keySlashed, 'value33', 3600);

        $hierarchicalFileStore->forget($flushSlashedKeyUsingSlash);

        static::assertEquals('value3', $hierarchicalFileStore->get($keyPiped));
        static::assertNull($hierarchicalFileStore->get($keySlashed));

        // stringable key

        $stringableKey1 = new StringableTestClassPhp7('stringable1|subkey1|sskey1|ssskey1');
        $hierarchicalFileStore->put($stringableKey1, 'value1', 3600);
        static::assertEquals('value1', $hierarchicalFileStore->get($stringableKey1));
        $hierarchicalFileStore->forget('stringable1|subkey1');
        static::assertNull($hierarchicalFileStore->get($stringableKey1));

        if (PHP_VERSION_ID >= 80000) {
            $stringableKey2 = new StringableTestClassPhp8('stringable2|subkey1|sskey1|ssskey1');
            $hierarchicalFileStore->put($stringableKey2, 'value1', 3600);
            static::assertEquals('value1', $hierarchicalFileStore->get($stringableKey2));
            $hierarchicalFileStore->forget('stringable2|subkey1');
            static::assertNull($hierarchicalFileStore->get($stringableKey2));
        }

        $hierarchicalFileStore->flush();
    }

    public function testHierarchicalCacheKeysInArrayStore(): void
    {
        /** @var AlternativeArrayCacheStoreWithLocks|Repository $arrayStore */
        $arrayStore = $this->getCache()->store('array');

        $arrayStore->flush();

        $keyPiped = 'key1|subkey1|sskey1|ssskey1';
        $keySlashed = 'key2/subkey2/sskey2/ssskey2';
        $flushPipedKeyUsingPipe = 'key1|subkey1';
        $flushPipedKeyUsingSlash = 'key1/subkey1';
        $flushSlashedKeyUsingPipe = 'key2|subkey2';
        $flushSlashedKeyUsingSlash = 'key2/subkey2';

        $arrayStore->put($keyPiped, 'value3', 3600);
        static::assertEquals('value3', $arrayStore->get($keyPiped));
        $arrayStore->put($keySlashed, 'value33', 3600);
        static::assertEquals('value33', $arrayStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipedKeyUsingPipe

        $arrayStore->forget($flushPipedKeyUsingPipe);

        static::assertNull($arrayStore->get($keyPiped));
        static::assertEquals('value33', $arrayStore->get($keySlashed));

        // $keyPiped -> flush by $flushPipiedKeyUsingSlash

        $arrayStore->put($keyPiped, 'value3', 3600);
        $arrayStore->put($keySlashed, 'value33', 3600);

        $arrayStore->forget($flushPipedKeyUsingSlash);

        static::assertNull($arrayStore->get($keyPiped));
        static::assertEquals('value33', $arrayStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingPipe

        $arrayStore->put($keyPiped, 'value3', 3600);
        $arrayStore->put($keySlashed, 'value33', 3600);

        $arrayStore->forget($flushSlashedKeyUsingPipe);

        static::assertEquals('value3', $arrayStore->get($keyPiped));
        static::assertNull($arrayStore->get($keySlashed));

        // $keySlashed -> flush by $flushSlashedKeyUsingSlash

        $arrayStore->put($keyPiped, 'value3', 3600);
        $arrayStore->put($keySlashed, 'value33', 3600);

        $arrayStore->forget($flushSlashedKeyUsingSlash);

        static::assertEquals('value3', $arrayStore->get($keyPiped));
        static::assertNull($arrayStore->get($keySlashed));

        // stringable key

        $stringableKey1 = new StringableTestClassPhp7('stringable1|subkey1|sskey1|ssskey1');
        $arrayStore->put($stringableKey1, 'value1', 3600);
        static::assertEquals('value1', $arrayStore->get($stringableKey1));
        $arrayStore->forget('stringable1|subkey1');
        static::assertNull($arrayStore->get($stringableKey1));

        // key that does not exist
        $notExistingKey1 = '|test|non_existing_key';
        static::assertNull($arrayStore->get($notExistingKey1));
        static::assertFalse($arrayStore->has($notExistingKey1));
        $notExistingKey2 = '/test/non_existing_key';
        static::assertNull($arrayStore->get($notExistingKey2));
        static::assertFalse($arrayStore->has($notExistingKey2));

        if (PHP_VERSION_ID >= 80000) {
            $stringableKey2 = new StringableTestClassPhp8('stringable2|subkey1|sskey1|ssskey1');
            $arrayStore->put($stringableKey2, 'value1', 3600);
            static::assertEquals('value1', $arrayStore->get($stringableKey2));
            $arrayStore->forget('stringable2|subkey1');
            static::assertNull($arrayStore->get($stringableKey2));
        }

        $arrayStore->flush();
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

    public function testFilesTouch(): void
    {
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');
        $fileStore->flush();
        $key1 = 'key1';
        $fileStore->put($key1, 'value1', 3600);
        static::assertEquals('value1', $fileStore->get($key1));
        $success = $fileStore->touch($key1, 7200);
        static::assertTrue($success);
        static::assertEquals('value1', $fileStore->get($key1));
        $item = $fileStore->getWrappedConnection()->getItem($fileStore->itemKey($key1));
        static::assertEquals('value1', $item->get());
        static::assertGreaterThanOrEqual(time() + 7100, $item->getExpirationTimestamp());
        static::assertLessThanOrEqual(time() + 7300, $item->getExpirationTimestamp());
    }

    public function testRedisTouch(): void
    {
        /** @var AlternativeRedisCacheStore|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        $redisStore->flush();
        $key1 = 'key1';
        $redisStore->put($key1, 'value1', 3600);
        static::assertEquals('value1', $redisStore->get($key1));
        $success = $redisStore->touch($key1, 7200);
        static::assertTrue($success);
        static::assertEquals('value1', $redisStore->get($key1));
        $item = $redisStore->getWrappedConnection()->getItem($redisStore->itemKey($key1));
        static::assertEquals('value1', $item->get());
        static::assertGreaterThanOrEqual(time() + 7100, $item->getExpirationTimestamp());
        static::assertLessThanOrEqual(time() + 7300, $item->getExpirationTimestamp());
    }

    public function testMemcachedTouch(): void
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        if (class_exists('\Memcached')) {
            /** @var AlternativeMemcachedCacheStore|Repository $memcachedStore */
            $memcachedStore = $this->getCache()->store('memcached');
            $memcachedStore->flush();
            $key1 = 'key1';
            $memcachedStore->put($key1, 'value1', 3600);
            static::assertEquals('value1', $memcachedStore->get($key1));
            $success = $memcachedStore->touch($key1, 7200);
            static::assertTrue($success);
            static::assertEquals('value1', $memcachedStore->get($key1));
            $item = $memcachedStore->getWrappedConnection()->getItem($memcachedStore->itemKey($key1));
            static::assertEquals('value1', $item->get());
            static::assertGreaterThanOrEqual(time() + 7100, $item->getExpirationTimestamp());
            static::assertLessThanOrEqual(time() + 7300, $item->getExpirationTimestamp());
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
        static::assertEquals('value1', $redisStore->get($key1));
        $keySha1 = sha1(str_replace('|', '!!!', 'root' . $redisStore->itemKey($key1) . '|'));
        //something like 'alternative_laravel_cache_test_database_13737195f3252a099b3ced6a237d2a128ccdac61'
        static::assertEquals([config('database.redis.options.prefix') . $keySha1], $this->getRedisKeys());
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
