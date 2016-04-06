<?php

namespace AlternativeLaravelCache\Provider;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeHierarchialFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStore;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class AlternativeCacheStoresServiceProvider extends ServiceProvider {

    static protected $redisDriverName = 'redis';
    static protected $fileDriverName = 'file';

    protected $defer = true;

    public function register() {
        $this->app->afterResolving('cache', function () {
            $this->addDriversToCacheManager();
        });
    }

    protected function addDriversToCacheManager() {
        $cacheManager = $this->app->make('cache');
        $this->addRedisCacheDriver($cacheManager);
        $this->addFileCacheDriver($cacheManager);
    }

    protected function addRedisCacheDriver(CacheManager $cacheManager) {
        $cacheManager->extend(static::$redisDriverName, function ($app, array $cacheConfig) use ($cacheManager) {
            $store = new AlternativeRedisCacheStore(
                $app['redis'],
                $this->getPrefix($cacheConfig),
                $this->getConnectionName($cacheConfig)
            );
            return $cacheManager->repository($store);
        });
    }

    protected function addFileCacheDriver(CacheManager $cacheManager) {
        $cacheManager->extend(static::$fileDriverName, function ($app, array $cacheConfig) use ($cacheManager) {
            $store = new AlternativeHierarchialFileCacheStore(
                new Filesystem($this->makeFileCacheAdapter($cacheConfig)),
                $this->getPrefix($cacheConfig)
            );
            return $cacheManager->repository($store);
        });
    }

    protected function makeFileCacheAdapter(array $cacheConfig) {
        switch (strtolower($cacheConfig['driver'])) {
            case 'file':
                return new Local($cacheConfig['path']);
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
    protected function getPrefix(array $config) {
        return array_get($config, 'prefix') ?: config('cache.prefix');
    }

    /**
     * @param array $cacheConfig
     * @return string
     */
    protected function getConnectionName(array $cacheConfig) {
        return array_get($cacheConfig, 'connection', 'default') ?: 'default';
    }
}