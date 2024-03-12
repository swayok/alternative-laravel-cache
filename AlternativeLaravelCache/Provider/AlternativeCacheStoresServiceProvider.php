<?php
/** @noinspection PhpVariableIsUsedOnlyInClosureInspection */

declare(strict_types=1);

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
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use League\Flysystem\Filesystem;

class AlternativeCacheStoresServiceProvider extends ServiceProvider
{
    protected $redisDriverName = 'redis';
    protected $memcacheDriverName = 'memcached';
    protected $fileDriverName = 'file';
    protected $hierarchialFileDriverName = 'hierarchial_file';

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
        if ($this->isRedisDriverEnabled() || $this->isPredisDriverEnabled()) {
            $this->addRedisCacheDriver($cacheManager, $hasLocks);
        }
        if ($this->isMemcachedDriverEnabled()) {
            $this->addMemcachedCacheDriver($cacheManager, $hasLocks);
        }
        if ($this->isFileDriverEnabled()) {
            $this->addFileCacheDriver($cacheManager, $hasLocks);
            if ($this->isHierarchialCacheEnabled()) {
                $this->addHierarchialFileCacheDriver($cacheManager, $hasLocks);
            }
        }
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

    protected function addFileCacheDriver(CacheManager $cacheManager, bool $hasLocks): void
    {
        $provider = $this;
        $cacheManager->extend(
            $this->fileDriverName,
            function (Application $app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
                $db = new Filesystem($provider->makeFileCacheAdapter($cacheConfig));
                if ($hasLocks) {
                    $store = new AlternativeFileCacheStoreWithLocks($db, $provider->getPrefix($cacheConfig));
                } else {
                    $store = new AlternativeFileCacheStore($db, $provider->getPrefix($cacheConfig));
                }
                $store->setLogger($app->make('log'));
                return $cacheManager->repository($store, $cacheConfig);
            }
        );
    }

    protected function addHierarchialFileCacheDriver(CacheManager $cacheManager, bool $hasLocks): void
    {
        $provider = $this;
        $cacheManager->extend(
            $this->hierarchialFileDriverName,
            function (Application $app, array $cacheConfig) use ($hasLocks, $provider, $cacheManager) {
                $db = new Filesystem($provider->makeFileCacheAdapter($cacheConfig));
                if ($hasLocks) {
                    $store = new AlternativeHierarchialFileCacheStoreWithLocks($db, $provider->getPrefix($cacheConfig));
                } else {
                    $store = new AlternativeHierarchialFileCacheStore($db, $provider->getPrefix($cacheConfig));
                }
                $store->setLogger($app->make('log'));
                return $cacheManager->repository($store, $cacheConfig);
            }
        );
    }

    /** @noinspection PhpFullyQualifiedNameUsageInspection */
    public function makeFileCacheAdapter(array $cacheConfig)
    {
        switch (strtolower($cacheConfig['driver'])) {
            case $this->fileDriverName:
            case $this->hierarchialFileDriverName:
                $permissions = $this->getNormalizedPermissions($cacheConfig);
                if (class_exists('League\Flysystem\Adapter\Local')) {
                    return new \League\Flysystem\Adapter\Local(
                        $cacheConfig['path'],
                        LOCK_EX,
                        \League\Flysystem\Adapter\Local::DISALLOW_LINKS,
                        $permissions
                    );
                }

                return new \League\Flysystem\Local\LocalFilesystemAdapter(
                    $cacheConfig['path'],
                    \League\Flysystem\UnixVisibility\PortableVisibilityConverter::fromArray($permissions, \League\Flysystem\Visibility::PUBLIC),
                    LOCK_EX,
                    \League\Flysystem\Local\LocalFilesystemAdapter::DISALLOW_LINKS
                );
            default:
                throw new InvalidArgumentException(
                    "File cache driver [{$cacheConfig['driver']}] is not supported.
                    You can add support for drivers by overwriting " . __CLASS__ . '->makeFileCacheAdapter() method'
                );
        }
    }

    protected function getNormalizedPermissions(array $cacheConfig): array
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

    protected function isFileDriverEnabled(): bool
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        return class_exists('\Cache\Adapter\Filesystem\FilesystemCachePool');
    }

    protected function isHierarchialCacheEnabled(): bool
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        return interface_exists('\Cache\Hierarchy\HierarchicalPoolInterface');
    }

    protected function isRedisDriverEnabled(): bool
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        return class_exists('\Cache\Adapter\Redis\RedisCachePool');
    }

    protected function isPredisDriverEnabled(): bool
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        return class_exists('\Cache\Adapter\Predis\PredisCachePool');
    }

    protected function isMemcachedDriverEnabled(): bool
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        return class_exists('\Cache\Adapter\Memcached\MemcachedCachePool');
    }

    /**
     * Returns cache prefix
     */
    public function getPrefix(array $config): string
    {
        return Arr::get($config, 'prefix') ?: config('cache.prefix');
    }

    public function getConnectionName(array $cacheConfig): string
    {
        return Arr::get($cacheConfig, 'connection', 'default') ?: 'default';
    }
}
