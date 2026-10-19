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
     * The classes that should be allowed during unserialization.
     *
     * @var array|bool|null
     */
    protected $serializableClasses;

    /**
     * Classes/interfaces whose subclasses are allowed during unserialization.
     *
     * @var string[]
     */
    protected $serializableBaseClasses = [];

    /**
     * @param \Memcached $db
     * @param string $prefix
     * @param string|null $connection
     * @param array|bool|null $serializableClasses
     * @param string[] $serializableBaseClasses
     */
    public function __construct(
        $db,
        $prefix,
        $connection = null,
        $serializableClasses = null,
        array $serializableBaseClasses = []
    ) {
        parent::__construct($db, $prefix, $connection);
        $this->serializableClasses = $serializableClasses;
        $this->serializableBaseClasses = $serializableBaseClasses;
    }

    /**
     * Wrap connection with MemcachedCachePool
     *
     * @return MemcachedCachePool|AbstractCachePool
     */
    public function wrapConnection()
    {
        $pool = new MemcachedCachePool($this->getDb());
        $pool->setSerializableClasses($this->serializableClasses);
        $pool->setSerializableBaseClasses($this->serializableBaseClasses);

        return $pool;
    }

    /**
     * Get the underlying Memcached connection.
     *
     * @return \Memcached
     */
    public function getMemcached()
    {
        return $this->getDb();
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
