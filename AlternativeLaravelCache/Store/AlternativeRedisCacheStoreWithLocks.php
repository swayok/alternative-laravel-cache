<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\RedisLock;
use Illuminate\Contracts\Cache\Lock;

class AlternativeRedisCacheStoreWithLocks extends AlternativeRedisCacheStore
{
    /**
     * The name of the connection that should be used for locks.
     *
     * @var string
     */
    protected $lockConnection;

    /**
     * Specify the name of the connection that should be used to manage locks.
     *
     * @param string $connection
     * @return $this
     */
    public function setLockConnection(string $connection)
    {
        $this->lockConnection = $connection;

        return $this;
    }

    /**
     * Get the Redis connection instance that should be used to manage locks.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function lockConnection()
    {
        return $this->getDb()->connection($this->lockConnection ?? $this->connection);
    }

    /**
     * Get a lock instance.
     */
    public function lock(string $name, int $seconds = 0, ?string $owner = null): Lock
    {
        return new RedisLock($this->lockConnection(), $this->prefix . $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     */
    public function restoreLock(string $name, string $owner): Lock
    {
        return $this->lock($name, 0, $owner);
    }
}
