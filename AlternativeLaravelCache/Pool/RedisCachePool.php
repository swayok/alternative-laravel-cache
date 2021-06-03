<?php

namespace AlternativeLaravelCache\Pool;

use Cache\Adapter\Common\Exception\CachePoolException;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;

/**
 * @property PhpRedisConnection|PhpRedisClusterConnection $cache
 */
class RedisCachePool extends \Cache\Adapter\Redis\RedisCachePool {
    
    /**
     * Wrap \Cache\Adapter\Redis\RedisCachePool constructor to use Laravel's connections with all their functionality
     * @param PhpRedisConnection|PhpRedisClusterConnection $cache
     */
    public function __construct($cache) {
        if (
            !($cache instanceof PhpRedisConnection)
            && !($cache instanceof PhpRedisClusterConnection)
        ) {
            throw new CachePoolException(
                'Cache instance must be of type \Illuminate\Redis\Connections\PhpRedisConnection or \Illuminate\Redis\Connections\PhpRedisClusterConnection'
            );
        }
        
        parent::__construct($cache->client());
        
        $this->cache = $cache;
    }
    
    public function __call($name, $arguments) {
        return call_user_func_array([$this->cache->client(), $name], $arguments);
    }
    
    protected function removeListItem($name, $key) {
        return $this->cache->lrem($name, 0, $key);
    }
}