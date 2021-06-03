<?php

namespace AlternativeLaravelCache\Pool;

use Cache\Adapter\Common\Exception\CachePoolException;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Illuminate\Redis\Connections\PredisConnection;

/**
 * @property PredisConnection|PredisClusterConnection $cache
 */
class PredisCachePool extends \Cache\Adapter\Predis\PredisCachePool {
    
    /**
     * Wrap \Cache\Adapter\Predis\PredisCachePool constructor to use Laravel's connections with all their functionality
     * @param PredisConnection|PredisClusterConnection $cache
     */
    public function __construct($cache) {
        if (
            !($cache instanceof PredisConnection)
            && !($cache instanceof PredisClusterConnection)
        ) {
            throw new CachePoolException(
                'Cache instance must be of type \Illuminate\Redis\Connections\PredisConnection or \Illuminate\Redis\Connections\PredisClusterConnection'
            );
        }
        
        parent::__construct($cache->client());
        
        $this->cache = $cache;
    }
    
    public function __call($name, $arguments) {
        return call_user_func_array([$this->cache->client(), $name], $arguments);
    }
}