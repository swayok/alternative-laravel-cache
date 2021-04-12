<?php

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\RedisLock;

class AlternativeRedisCacheStoreWithLocks extends AlternativeRedisCacheStore {
    
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
    public function setLockConnection($connection) {
        $this->lockConnection = $connection;
        
        return $this;
    }
    
    /**
     * Get the Redis connection instance that should be used to manage locks.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function lockConnection() {
        return $this->getDb()
            ->connection($this->lockConnection ?? $this->connection);
    }
    
    /**
     * Get a lock instance.
     *
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null) {
        return new RedisLock($this->lockConnection(), $this->prefix . $name, $seconds, $owner);
    }
    
    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param string $name
     * @param string $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restoreLock($name, $owner) {
        return $this->lock($name, 0, $owner);
    }
}
