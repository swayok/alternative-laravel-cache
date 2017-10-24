<?php

namespace AlternativeLaravelCache\Provider;

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
        $provider = $this;
        $cacheManager->extend(static::$redisDriverName, function ($app, array $cacheConfig) use ($provider, $cacheManager) {
            $store = new AlternativeRedisCacheStore(
                $app['redis'],
                $provider::getPrefix($cacheConfig),
                $provider::getConnectionName($cacheConfig)
            );
            return $cacheManager->repository($store);
        });
    }

    protected function addFileCacheDriver(CacheManager $cacheManager) {
        $provider = $this;
        $cacheManager->extend(static::$fileDriverName, function ($app, array $cacheConfig) use ($provider, $cacheManager) {
            $db = new Filesystem($provider::makeFileCacheAdapter($cacheConfig));
            $store = new AlternativeHierarchialFileCacheStore($db, $provider::getPrefix($cacheConfig));
            return $cacheManager->repository($store);
        });
    }

    static public function makeFileCacheAdapter(array $cacheConfig) {
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
    static public function getPrefix(array $config) {
        return array_get($config, 'prefix') ?: config('cache.prefix');
    }

    /**
     * @param array $cacheConfig
     * @return string
     */
    static public function getConnectionName(array $cacheConfig) {
        return array_get($cacheConfig, 'connection', 'default') ?: 'default';
    }
}