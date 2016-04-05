<?php

namespace AlternativeLaravelCache\Provider;

use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
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
        $this->app->booted(function () {
            $cacheManager = $this->getCacheManager();
            // Lets replace default redis cache storage (it is ugly for tagging)
            $cacheManager->extend(static::$redisDriverName, function ($app, array $cacheConfig) {
                return $this->makeRedisCacheRepository($app, $cacheConfig);
            });
            // and file cache storage too
            $cacheManager->extend(static::$fileDriverName, function ($app, array $cacheConfig) {
                return $this->makeFileCacheRepository($app, $cacheConfig);
            });
        });
    }

    protected function makeRedisCacheRepository($app, array $cacheConfig) {
        return $this->getCacheManager()->repository(new AlternativeRedisCacheStore(
            $app['redis'],
            $this->getPrefix($cacheConfig),
            $this->getConnectionName($cacheConfig)
        ));
    }

    protected function makeFileCacheRepository($app, array $cacheConfig) {
        return $this->getCacheManager()->repository(new AlternativeFileCacheStore(
            new Filesystem($this->makeFileCacheAdapter($cacheConfig)),
            $this->getPrefix($cacheConfig)
        ));
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
     * @return CacheManager
     */
    protected function getCacheManager() {
        return $this->app['cache'];
    }

    /**
     * @param array $cacheConfig
     * @return string
     */
    protected function getConnectionName(array $cacheConfig) {
        return array_get($cacheConfig, 'connection', 'default') ?: 'default';
    }
}