<?php

namespace Tests\Feature;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeFileCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStore;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStore;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStoreWithLocks;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Tests\TestCase;

class AlternativeLaravelCacheTest extends TestCase {

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
    
    /**
     * @return CacheManager
     */
    private function getCache() {
        return $this->app['cache'];
    }

    public function testNormalCache() {
        /** @var AlternativeRedisCacheStore|Repository $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStore|Repository $fileStore */
        $fileStore = $this->getCache()->store('file');

        $redisStore->flush();
        $fileStore->flush();

        $key = 'key1|subkey/sskey\\ssskey';
        $redisStore->put($key, 'value1', 1);
        self::assertEquals('value1', $redisStore->get($key));
        $fileStore->put($key, 'value2', 1);
        self::assertEquals('value2', $fileStore->get($key));
    }
    
    public function testMemcachedCache() {
        if (class_exists('\Memcached')) {
            /** @var AlternativeMemcachedCacheStore|Repository $memcachedStore */
            $memcachedStore = $this->getCache()
                ->store('memcached');
    
            $memcachedStore->flush();
    
            $key = 'key1|subkey/sskey\\ssskey';
            $memcachedStore->put($key, 'value3', 1);
            self::assertEquals('value3', $memcachedStore->get($key));
        } else {
            static::assertTrue(true);
        }
    }

    public function testHierarchialFileCache() {
        /** @var AlternativeFileCacheStore|Repository $hierarchialFileStore */
        $hierarchialFileStore = $this->getCache()->store('hierarchial_file');
        $hierarchialFileStore->flush();
        $key1 = 'key1|subkey1';
        $hierarchialFileStore->put($key1, 'value1', 1);
        self::assertEquals('value1', $hierarchialFileStore->get($key1));
        $key2 = 'key1|subkey2';
        $hierarchialFileStore->put($key2, 'value2', 1);
        self::assertEquals('value2', $hierarchialFileStore->get($key2));
        $key3 = 'key2|subkey1';
        $hierarchialFileStore->put($key3, 'value3', 1);
        self::assertEquals('value3', $hierarchialFileStore->get($key3));
        $hierarchialFileStore->forget('key1');
        self::assertNull($hierarchialFileStore->get($key1));
        self::assertNull($hierarchialFileStore->get($key2));
        self::assertEquals('value3', $hierarchialFileStore->get($key3));
    }

    public function testTaggedCache() {
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

        $redisStore->tags(['tag1', 'tag2'])->put($key1, 'value1', 1);
        self::assertEquals('value1', $redisStore->get($key1));
        $redisStore->tags(['tag3'])->put($key2, 'value11', 1);
        self::assertEquals('value11', $redisStore->get($key2));

        $fileStore->tags(['tag1', 'tag2'])->put($key1, 'value2', 1);
        self::assertEquals('value2', $fileStore->get($key1));
        $fileStore->tags(['tag3'])->put($key2, 'value22', 1);
        self::assertEquals('value22', $fileStore->get($key2));

        $hierarchialFileStore->tags(['tag1', 'tag2'])->put($key1, 'value3', 1);
        self::assertEquals('value3', $hierarchialFileStore->get($key1));
        $hierarchialFileStore->tags(['tag3'])->put($key2, 'value33', 1);
        self::assertEquals('value33', $hierarchialFileStore->get($key2));

        $redisStore->tags(['tag1'])->flush();
        $fileStore->tags(['tag1'])->flush();
        $hierarchialFileStore->tags(['tag1'])->flush();

        self::assertEquals('value11', $redisStore->get($key2));
        self::assertEquals('value22', $fileStore->get($key2));
        self::assertEquals('value33', $hierarchialFileStore->get($key2));
        self::assertNull($redisStore->get($key1));
        self::assertNull($fileStore->get($key1));
        self::assertNull($hierarchialFileStore->get($key1));
    }
    
    public function testLocks() {
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
    
    public function testMemcachedLocks() {
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
    
}
