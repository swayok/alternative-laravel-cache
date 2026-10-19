<?php
/** @noinspection PhpVariableIsUsedOnlyInClosureInspection */

declare(strict_types=1);

namespace AlternativeLaravelCache\Provider;

use AlternativeLaravelCache\Store\AlternativeArrayCacheStore;
use AlternativeLaravelCache\Store\AlternativeArrayCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeFileCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeHierarchicalFileCacheStore;
use AlternativeLaravelCache\Store\AlternativeHierarchicalFileCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStore;
use AlternativeLaravelCache\Store\AlternativeMemcachedCacheStoreWithLocks;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStore;
use AlternativeLaravelCache\Store\AlternativeRedisCacheStoreWithLocks;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class AlternativeCacheStoresServiceProvider extends ServiceProvider
{
    protected $redisDriverName = 'redis';
    protected $memcacheDriverName = 'memcached';
    protected $arrayDriverName = 'array';
    protected $fileDriverName = 'file';
    protected $hierarchicalFileDriverName = 'hierarchical_file';

    protected $defaultPermissions = [
        'file' => [
            'public' => 0644,
            'private' => 0644,
        ],
        'dir' => [
            'public' => 0755,
            'private' => 0755,
        ],
    ];

    public function register(): void
    {
        $this->app->afterResolving('cache', function () {
            $this->addDriversToCacheManager();
        });
    }

    protected function addDriversToCacheManager(): void
    {
        $cacheManager = $this->app->make('cache');
        $hasLocks = trait_exists('\Illuminate\Cache\HasCacheLock');
        $this->addRedisCacheDriver($cacheManager, $hasLocks);
        $this->addMemcachedCacheDriver($cacheManager, $hasLocks);
        $this->addArrayCacheDriver($cacheManager, $hasLocks);
        $this->addFileCacheDriver($cacheManager, $hasLocks);
        $this->addHierarchicalFileCacheDriver($cacheManager, $hasLocks);
    }

    protected function addRedisCacheDriver(CacheManager $cacheManager, bool $hasLocks): void
    {
        $provider = $this;
        $cacheManager->extend(
            $this->redisDriverName,
            function (Application $app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
                if ($hasLocks) {
                    $store = new AlternativeRedisCacheStoreWithLocks(
                        $app['redis'],
                        $provider->getPrefix($cacheConfig),
                        $provider->getConnectionName($cacheConfig)
                    );
                } else {
                    $store = new AlternativeRedisCacheStore(
                        $app['redis'],
                        $provider->getPrefix($cacheConfig),
                        $provider->getConnectionName($cacheConfig)
                    );
                }
                $store->setLogger($app->make('log'));
                return $cacheManager->repository($store, $cacheConfig);
            }
        );
    }

    protected function addMemcachedCacheDriver(CacheManager $cacheManager, bool $hasLocks): void
    {
        $provider = $this;
        $cacheManager->extend(
            $this->memcacheDriverName,
            function (Application $app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
                $memcached = $this->app['memcached.connector']->connect(
                    $cacheConfig['servers'],
                    $cacheConfig['persistent_id'] ?? null,
                    $cacheConfig['options'] ?? [],
                    array_filter($cacheConfig['sasl'] ?? [])
                );
                if ($hasLocks) {
                    $store = new AlternativeMemcachedCacheStoreWithLocks(
                        $memcached,
                        $provider->getPrefix($cacheConfig),
                        $provider->getConnectionName($cacheConfig)
                    );
                } else {
                    $store = new AlternativeMemcachedCacheStore(
                        $memcached,
                        $provider->getPrefix($cacheConfig),
                        $provider->getConnectionName($cacheConfig)
                    );
                }
                $store->setLogger($app->make('log'));
                return $cacheManager->repository($store, $cacheConfig);
            }
        );
    }

    protected function addArrayCacheDriver(CacheManager $cacheManager, bool $hasLocks): void
    {
        $provider = $this;
        $cacheManager->extend(
            $this->arrayDriverName,
            function (Application $app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
                $serialize = $cacheConfig['serialize'] ?? false;
                $serializableClasses = $this->app['config']['cache.serializable_classes'] ?? null;
                if ($hasLocks) {
                    $store = new AlternativeArrayCacheStoreWithLocks($serialize, $serializableClasses);
                } else {
                    $store = new AlternativeArrayCacheStore($serialize, $serializableClasses);
                }
                $store->setLogger($app->make('log'));
                return $cacheManager->repository($store, $cacheConfig);
            }
        );
    }

    protected function addFileCacheDriver(CacheManager $cacheManager, bool $hasLocks): void
    {
        $provider = $this;
        $cacheManager->extend(
            $this->fileDriverName,
            function (Application $app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
                $permissions = $provider->getNormalizedPermissions($cacheConfig);
                $storeClass = $hasLocks ? AlternativeFileCacheStoreWithLocks::class : AlternativeFileCacheStore::class;
                $store = new $storeClass(
                    $app['files'],
                    $provider->getPrefix($cacheConfig),
                    $cacheConfig['path'],
                    $cacheConfig['permission'] ?? $permissions['file']['public'],
                    true,
                    $permissions['dir']['public']
                );
                if ($hasLocks) {
                    $store->setLockDirectory($cacheConfig['lock_path'] ?? null);
                }
                $store->setLogger($app->make('log'));
                return $cacheManager->repository($store, $cacheConfig);
            }
        );
    }

    protected function addHierarchicalFileCacheDriver(CacheManager $cacheManager, bool $hasLocks): void
    {
        $provider = $this;
        $cacheManager->extend(
            $this->hierarchicalFileDriverName,
            function (Application $app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
                $permissions = $provider->getNormalizedPermissions($cacheConfig);
                $storeClass = $hasLocks
                    ? AlternativeHierarchicalFileCacheStoreWithLocks::class
                    : AlternativeHierarchicalFileCacheStore::class;
                $store = new $storeClass(
                    $app['files'],
                    $provider->getPrefix($cacheConfig),
                    $cacheConfig['path'],
                    $cacheConfig['permission'] ?? $permissions['file']['public'],
                    $permissions['dir']['public']
                );
                $store->setLogger($app->make('log'));
                return $cacheManager->repository($store, $cacheConfig);
            }
        );
    }

    public function getNormalizedPermissions(array $cacheConfig): array
    {
        $configPermissions = Arr::get($cacheConfig, 'permissions');
        $permissionsMap = $this->defaultPermissions;
        if (!empty($configPermissions)) {
            if (is_array($configPermissions)) {
                if (isset($configPermissions['file']) && is_int($configPermissions['file'])) {
                    if (!is_array($configPermissions['file'])) {
                        $permissionsMap['file']['public'] = $permissionsMap['file']['private'] = $configPermissions['file'];
                    } else {
                        $permissionsMap['file'] = $configPermissions['file'];
                    }
                }
                if (isset($configPermissions['dir']) && is_int($configPermissions['dir'])) {
                    if (!is_array($configPermissions['dir'])) {
                        $permissionsMap['dir']['public'] = $permissionsMap['dir']['private'] = $configPermissions['dir'];
                    } else {
                        $permissionsMap['dir'] = $configPermissions['dir'];
                    }
                }
            } elseif (is_int($configPermissions)) {
                $permissionsMap['file']['public'] = $permissionsMap['file']['private'] = $configPermissions;
            }
        }
        return $permissionsMap;
    }

    /**
     * Returns cache prefix
     */
    public function getPrefix(array $config): string
    {
        return Arr::get($config, 'prefix') ?? config('cache.prefix');
    }

    public function getConnectionName(array $cacheConfig): string
    {
        return Arr::get($cacheConfig, 'connection', 'default') ?: 'default';
    }
}
