<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Predis\PredisCachePool;
use Cache\Adapter\Redis\RedisCachePool;
use Cache\Hierarchy\HierarchicalPoolInterface;
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
     * Wrap connection with PredisCachePool or RedisCachePool
     *
     * @return PredisCachePool|RedisCachePool|AbstractCachePool
     */
    public function wrapConnection()
    {
        if ($this->isPhpRedis()) {
            // PHPRedis extension client
            return new RedisCachePool($this->getConnection());
        }

        return new PredisCachePool($this->getConnection());
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
        return $this
            ->getDb()
            ->connection($this->connection)
            ->client();
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
