<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalPoolInterface;
use AlternativeLaravelCache\Vendors\Memcached\MemcachedCachePool;

/**
 * @method \Memcached getDb()
 */
class AlternativeMemcachedCacheStore extends AlternativeCacheStore
{
    /**
     * The Memcached database connection.
     *
     * @var \Memcached
     */
    protected $db;

    /**
     * Wrap connection with MemcachedCachePool
     *
     * @return MemcachedCachePool|AbstractCachePool
     */
    public function wrapConnection()
    {
        return new MemcachedCachePool($this->getDb());
    }

    public function setPrefix(string $prefix): void
    {
        // not allowed chars: "{}()/\@"
        parent::setPrefix(preg_replace('%[{}()/@:\\\]%', '_', $prefix));
    }

    public function fixItemKey(string $key): string
    {
        // not allowed characters: {}()/\@:
        return preg_replace('%[{}()/@:\\\]%', '-', parent::fixItemKey($key));
    }

    public function getHierarchySeparator(): string
    {
        return HierarchicalPoolInterface::HIERARCHY_SEPARATOR;
    }
}
