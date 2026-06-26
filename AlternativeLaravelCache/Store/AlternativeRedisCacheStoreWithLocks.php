<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\RedisLock;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;

class AlternativeRedisCacheStoreWithLocks extends AlternativeRedisCacheStore implements LockProvider
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
    public function lock($name, $seconds = 0, $owner = null): Lock
    {
        return new RedisLock($this->lockConnection(), $this->prefix . $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     */
    public function restoreLock($name, $owner): Lock
    {
        return $this->lock($name, 0, $owner);
    }

    /**
     * Remove all locks from the store.
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function flushLocks(): bool
    {
        if (! $this->hasSeparateLockStore()) {
            throw new \RuntimeException('Flushing locks is only supported when the lock store is separate from the cache store.');
        }

        $this->lockConnection()->flushdb();

        return true;
    }

    /**
     * Determine if the lock store is separate from the cache store.
     *
     * @return bool
     */
    public function hasSeparateLockStore(): bool
    {
        return $this->lockConnection !== $this->connection;
    }
}
