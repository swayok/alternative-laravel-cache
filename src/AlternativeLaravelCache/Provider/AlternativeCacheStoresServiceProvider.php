<?php

namespace AlternativeLaravelCache\Provider;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeHierarchialFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStore;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class AlternativeCacheStoresServiceProvider extends ServiceProvider {

    static protected $redisDriverName = 'redis';
    static protected $fileDriverName = 'file';
    static protected $hierarchialFileDriverName = 'hierarchial_file';

    public function register() {
        $this->app->afterResolving('cache', function () {
            $this->addDriversToCacheManager();
        });
    }

    protected function addDriversToCacheManager() {
        $cacheManager = $this->app->make('cache');
        $this->addRedisCacheDriver($cacheManager);
        $this->addFileCacheDriver($cacheManager);
        $this->addHierarchialFileCacheDriver($cacheManager);
    }

    protected function addRedisCacheDriver(CacheManager $cacheManager) {
        $provider = $this;
        $cacheManager->extend(static::$redisDriverName, function ($app, array $cacheConfig) use ($provider, $cacheManager) {
            $store = new AlternativeRedisCacheStore(
                $app['redis'],
                $provider::getPrefix($cacheConfig),
                $provider::getConnectionName($cacheConfig)
            );
            $store->getWrappedConnection()->setLogger(app('log'));
            return $cacheManager->repository($store);
        });
    }

    protected function addFileCacheDriver(CacheManager $cacheManager) {
        $provider = $this;
        $cacheManager->extend(static::$fileDriverName, function ($app, array $cacheConfig) use ($provider, $cacheManager) {
            $db = new Filesystem($provider::makeFileCacheAdapter($cacheConfig));
            $store = new AlternativeFileCacheStore($db, $provider::getPrefix($cacheConfig));
            $store->getWrappedConnection()->setLogger(app('log'));
            return $cacheManager->repository($store);
        });
    }

    protected function addHierarchialFileCacheDriver(CacheManager $cacheManager) {
        $provider = $this;
        $cacheManager->extend(static::$hierarchialFileDriverName, function ($app, array $cacheConfig) use ($provider, $cacheManager) {
            $db = new Filesystem($provider::makeFileCacheAdapter($cacheConfig));
            $store = new AlternativeHierarchialFileCacheStore($db, $provider::getPrefix($cacheConfig));
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
