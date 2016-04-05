<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use Cache\Adapter\Predis\PredisCachePool;

class AlternativeRedisCacheStore extends AlternativeCacheStore {

    /**
     * The Redis database connection.
     *
     * @var \Illuminate\Redis\Database
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
        return $this->getDb()->connection($this->connection);
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

}