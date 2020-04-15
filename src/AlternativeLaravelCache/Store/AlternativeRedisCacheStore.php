<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Predis\PredisCachePool;
use Cache\Adapter\Redis\RedisCachePool;
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
        return $this->getDb()->connection($this->connection)->client();
    }

    /**
     * Set the connection name to be used.
     *
     * @param  string $connection
     * @return void
     */
    public function setConnection($connection) {
        parent::setConnection($connection);
        $this->wrappedConnection = null;
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


}
