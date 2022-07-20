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
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

class AlternativeCacheStoresServiceProvider extends ServiceProvider {

    static protected string $redisDriverName = 'redis';
    static protected string $memcacheDriverName = 'memcached';
    static protected string $fileDriverName = 'file';
    static protected string $hierarchialFileDriverName = 'hierarchial_file';
    
    static protected array $defaultPermissions = [
        'file' => [
            'public' => 0644,
            'private' => 0644
        ],
        'dir' => [
            'public' => 0755,
            'private' => 0755
        ]
    ];

    public function register(): void {
        $this->app->afterResolving('cache', function () {
            $this->addDriversToCacheManager();
        });
    }

    protected function addDriversToCacheManager(): void {
        $cacheManager = $this->app->make('cache');
        $hasLocks = trait_exists('\Illuminate\Cache\HasCacheLock');
        $this->addRedisCacheDriver($cacheManager, $hasLocks);
        $this->addMemcachedCacheDriver($cacheManager, $hasLocks);
        $this->addFileCacheDriver($cacheManager, $hasLocks);
        $this->addHierarchialFileCacheDriver($cacheManager, $hasLocks);
    }

    protected function addRedisCacheDriver(CacheManager $cacheManager, bool $hasLocks): void {
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
    
    protected function addMemcachedCacheDriver(CacheManager $cacheManager, bool $hasLocks): void {
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

    protected function addFileCacheDriver(CacheManager $cacheManager, bool $hasLocks): void {
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

    protected function addHierarchialFileCacheDriver(CacheManager $cacheManager, bool $hasLocks): void {
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

    public static function makeFileCacheAdapter(array $cacheConfig): LocalFilesystemAdapter {
        switch (strtolower($cacheConfig['driver'])) {
            case static::$fileDriverName:
            case static::$hierarchialFileDriverName:
                return new LocalFilesystemAdapter(
                    $cacheConfig['path'],
                    PortableVisibilityConverter::fromArray(static::getNormalizedPermissions($cacheConfig), Visibility::PUBLIC),
                    LOCK_EX,
                    LocalFilesystemAdapter::DISALLOW_LINKS,
                );
            default:
                throw new InvalidArgumentException("File cache driver [{$cacheConfig['driver']}] is not supported.
                    You can add support for drivers by overwriting " . __CLASS__ . '->makeFileCacheAdapter() method');
        }
    }
    
    private static function getNormalizedPermissions(array $cacheConfig): array {
        $configPermissions = Arr::get($cacheConfig, 'permissions');
        $permissionsMap = static::$defaultPermissions;
        if (!empty($configPermissions)) {
            if (is_array($configPermissions)) {
                if (isset($configPermissions['file']) && is_int($configPermissions['file'])) {
                    $permissionsMap['file']['public'] = $permissionsMap['file']['private'] = $configPermissions['file'];
                }
                if (isset($configPermissions['dir']) && is_int($configPermissions['dir'])) {
                    $permissionsMap['dir']['public'] = $permissionsMap['dir']['private'] = $configPermissions['dir'];
                }
            } else if (is_int($configPermissions)) {
                $permissionsMap['file']['public'] = $permissionsMap['file']['private'] = $configPermissions;
            }
        }
        return $permissionsMap;
    }

    /**
     * Returns cache prefix
     */
    public static function getPrefix(array $config): string {
        return Arr::get($config, 'prefix') ?: config('cache.prefix');
    }

    public static function getConnectionName(array $cacheConfig): string {
        return Arr::get($cacheConfig, 'connection', 'default') ?: 'default';
    }
}
