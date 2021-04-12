<?php

namespace AlternativeLaravelCache\Provider;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeFileCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeHierarchialFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeHierarchialFileCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStore;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStore;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStoreWithLocks;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class AlternativeCacheStoresServiceProvider extends ServiceProvider {

    static protected $redisDriverName = 'redis';
    static protected $memcacheDriverName = 'memcached';
    static protected $fileDriverName = 'file';
    static protected $hierarchialFileDriverName = 'hierarchial_file';

    public function register() {
        $this->app->afterResolving('cache', function () {
            $this->addDriversToCacheManager();
        });
    }

    protected function addDriversToCacheManager() {
        $cacheManager = $this->app->make('cache');
        $hasLocks = trait_exists('\Illuminate\Cache\HasCacheLock');
        $this->addRedisCacheDriver($cacheManager, $hasLocks);
        $this->addMemcachedCacheDriver($cacheManager, $hasLocks);
        $this->addFileCacheDriver($cacheManager, $hasLocks);
        $this->addHierarchialFileCacheDriver($cacheManager, $hasLocks);
    }

    protected function addRedisCacheDriver(CacheManager $cacheManager, bool $hasLocks) {
        $provider = $this;
        $cacheManager->extend(static::$redisDriverName, function ($app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
            if ($hasLocks) {
                $store = new AlternativeRedisCacheStoreWithLocks(
                    $app['redis'],
                    $provider::getPrefix($cacheConfig),
                    $provider::getConnectionName($cacheConfig)
                );
            } else {
                $store = new AlternativeRedisCacheStore(
                    $app['redis'],
                    $provider::getPrefix($cacheConfig),
                    $provider::getConnectionName($cacheConfig)
                );
            }
            $store->getWrappedConnection()->setLogger(app('log'));
            return $cacheManager->repository($store);
        });
    }
    
    protected function addMemcachedCacheDriver(CacheManager $cacheManager, bool $hasLocks) {
        $provider = $this;
        $cacheManager->extend(static::$memcacheDriverName, function ($app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
            $memcached = $this->app['memcached.connector']->connect(
                $cacheConfig['servers'],
                $cacheConfig['persistent_id'] ?? null,
                $cacheConfig['options'] ?? [],
                array_filter($cacheConfig['sasl'] ?? [])
            );
            if ($hasLocks) {
                $store = new AlternativeMemcachedCacheStoreWithLocks(
                    $memcached,
                    $provider::getPrefix($cacheConfig),
                    $provider::getConnectionName($cacheConfig)
                );
            } else {
                $store = new AlternativeMemcachedCacheStore(
                    $memcached,
                    $provider::getPrefix($cacheConfig),
                    $provider::getConnectionName($cacheConfig)
                );
            }
            $store->getWrappedConnection()->setLogger(app('log'));
            return $cacheManager->repository($store);
        });
    }

    protected function addFileCacheDriver(CacheManager $cacheManager, bool $hasLocks) {
        $provider = $this;
        $cacheManager->extend(static::$fileDriverName, function ($app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
            $db = new Filesystem($provider::makeFileCacheAdapter($cacheConfig));
            if ($hasLocks) {
                $store = new AlternativeFileCacheStoreWithLocks($db, $provider::getPrefix($cacheConfig));
            } else {
                $store = new AlternativeFileCacheStore($db, $provider::getPrefix($cacheConfig));
            }
            $store->getWrappedConnection()->setLogger(app('log'));
            return $cacheManager->repository($store);
        });
    }

    protected function addHierarchialFileCacheDriver(CacheManager $cacheManager, bool $hasLocks) {
        $provider = $this;
        $cacheManager->extend(static::$hierarchialFileDriverName, function ($app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
            $db = new Filesystem($provider::makeFileCacheAdapter($cacheConfig));
            if ($hasLocks) {
                $store = new AlternativeHierarchialFileCacheStoreWithLocks($db, $provider::getPrefix($cacheConfig));
            } else {
                $store = new AlternativeHierarchialFileCacheStore($db, $provider::getPrefix($cacheConfig));
            }
            $store->getWrappedConnection()->setLogger(app('log'));
            return $cacheManager->repository($store);
        });
    }

    static public function makeFileCacheAdapter(array $cacheConfig) {
        switch (strtolower($cacheConfig['driver'])) {
            case static::$fileDriverName:
            case static::$hierarchialFileDriverName:
                return new Local($cacheConfig['path'], LOCK_EX, Local::DISALLOW_LINKS, Arr::get($cacheConfig, 'permissions') ?: []);
            default:
                throw new InvalidArgumentException("File cache driver [{$cacheConfig['driver']}] is not supported.
                    You can add support for drivers by overwriting " . __CLASS__ . '->makeFileCacheAdapter() method');
        }
    }

    /**
     * Returns cache prefix
     * @param array $config
     * @return string
     */
    static public function getPrefix(array $config) {
        return Arr::get($config, 'prefix') ?: config('cache.prefix');
    }

    /**
     * @param array $cacheConfig
     * @return string
     */
    static public function getConnectionName(array $cacheConfig) {
        return Arr::get($cacheConfig, 'connection', 'default') ?: 'default';
    }
}
