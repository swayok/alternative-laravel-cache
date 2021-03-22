<?php

namespace Tests\Feature;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStore;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStore;
use Illuminate\Cache\CacheManager;
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
        /** @var AlternativeRedisCacheStore $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStore $fileStore */
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
            /** @var AlternativeMemcachedCacheStore $memcachedStore */
            $memcachedStore = $this->getCache()
                ->store('memcached');
    
            $memcachedStore->flush();
    
            $key = 'key1|subkey/sskey\\ssskey';
            $memcachedStore->put($key, 'value3', 1);
            self::assertEquals('value3', $memcachedStore->get($key));
        }
    }

    public function testHierarchialFileCache() {
        /** @var AlternativeFileCacheStore $hierarchialFileStore */
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
        /** @var AlternativeRedisCacheStore $redisStore */
        $redisStore = $this->getCache()->store('redis');
        /** @var AlternativeFileCacheStore $fileStore */
        $fileStore = $this->getCache()->store('file');
        /** @var AlternativeFileCacheStore $hierarchialFileStore */
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
}
