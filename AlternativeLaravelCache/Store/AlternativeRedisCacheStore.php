<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalPoolInterface;
use AlternativeLaravelCache\Vendors\Redis\RedisCachePool;
use AlternativeLaravelCache\Vendors\Redis\PredisCachePool;
use Illuminate\Redis\RedisManager;

/**
 * @method RedisManager getDb()
 */
class AlternativeRedisCacheStore extends AlternativeCacheStore
{
    /**
     * The Redis database connection.
     *
     * @var RedisManager
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
     * @param RedisManager $db
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
     * Wrap connection with PredisCachePool or RedisCachePool
     *
     * @return PredisCachePool|RedisCachePool|AbstractCachePool
     */
    public function wrapConnection()
    {
        if ($this->isPhpRedis()) {
            // PHPRedis extension client
            $pool = new RedisCachePool($this->getConnection());
        } else {
            $pool = new PredisCachePool($this->getConnection());
        }
        $pool->setSerializableClasses($this->serializableClasses);
        $pool->setSerializableBaseClasses($this->serializableBaseClasses);

        return $pool;
    }

    protected function isPhpRedis(): bool
    {
        $connectionClass = get_class($this->getConnection());
        return $connectionClass === 'Redis' || $connectionClass === 'RedisCluster';
    }

    /**
     * Get the Redis connection client
     *
     * @return \Predis\Client|\Predis\ClientInterface|\Redis
     */
    public function getConnection()
    {
        return $this->connection()->client();
    }

    /**
     * Get the Redis connection instance.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function connection()
    {
        return $this->getDb()->connection($this->connection);
    }

    /**
     * Get the Redis database instance.
     *
     * @return RedisManager
     */
    public function getRedis()
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
