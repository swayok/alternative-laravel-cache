<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Predis\PredisCachePool;
use Cache\Adapter\Redis\RedisCachePool;
use Illuminate\Cache\RedisLock;
use Illuminate\Redis\RedisManager;

/**
 * @method RedisManager getDb()
 */
class AlternativeRedisCacheStore extends AlternativeCacheStore {
    
    /**
     * The Redis database connection.
     *
     * @var RedisManager
     */
    protected $db;
    
    /**
     * The name of the connection that should be used for locks.
     *
     * @var string
     */
    protected $lockConnection;
    
    /**
     * Wrap Redis connection with PredisCachePool
     *
     * @return PredisCachePool|RedisCachePool|AbstractCachePool
     */
    public function wrapConnection() {
        $connection = $this->getConnection();
        if (get_class($connection) === 'Redis') {
            // PHPRedis extension client
            return new RedisCachePool($connection);
        } else {
            return new PredisCachePool($connection);
        }
    }
    
    /**
     * Get the Redis connection client
     *
     * @return \Predis\Client|\Predis\ClientInterface|\Redis
     */
    public function getConnection() {
        return $this
            ->getDb()
            ->connection($this->connection)
            ->client();
    }
    
    public function setPrefix($prefix) {
        // not allowed chars: "{}()/\@"
        parent::setPrefix(preg_replace('%[\{\}\(\)\/@:\\\]%', '_', $prefix));
    }
    
    /**
     * Fix original item key to be compatible with cache storeage wrapper.
     * Used in some stores to fix not allowed chars usage in key name
     *
     * @param $key
     * @return mixed
     */
    public function fixItemKey($key) {
        // not allowed characters: {}()/\@:
        return preg_replace('%[\{\}\(\)\/@:\\\]%', '-', parent::fixItemKey($key));
    }
    
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
