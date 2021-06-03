<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Pool\PredisCachePool;
use AlternativeLaravelCache\Pool\RedisCachePool;
use Cache\Adapter\Common\AbstractCachePool;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Illuminate\Redis\Connections\PredisConnection;
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
        $connectionClass = get_class($connection);
    
        if (
            $connectionClass === 'Illuminate\Redis\Connections\PhpRedisConnection'
            || $connectionClass === 'Illuminate\Redis\Connections\PhpRedisClusterConnection'
        ) {
            // PHPRedis extension client
            return new RedisCachePool($connection);
        } else {
            return new PredisCachePool($connection);
        }
    }
    
    /**
     * Get the Redis connection client
     *
     * @return PhpRedisConnection|PhpRedisClusterConnection|PredisConnection|PredisClusterConnection|Connection
     */
    public function getConnection() {
        return $this
            ->getDb()
            ->connection($this->connection);
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
