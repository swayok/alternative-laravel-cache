<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use Cache\Adapter\Predis\PredisCachePool;
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
     * @return PredisCachePool
     */
    public function wrapConnection() {
        return new PredisCachePool($this->getConnection());
    }

    /**
     * Get the Redis connection client
     *
     * @return \Predis\Client|\Predis\ClientInterface
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