<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Memcached\MemcachedCachePool;
use Illuminate\Cache\MemcachedLock;

/**
 * @method \Memcached getDb()
 */
class AlternativeMemcachedCacheStore extends AlternativeCacheStore {
    
    /**
     * The Memcached database connection.
     *
     * @var \Memcached
     */
    protected $db;
    
    /**
     * Wrap Redis connection with MemcachedCachePool
     *
     * @return MemcachedCachePool|AbstractCachePool
     */
    public function wrapConnection() {
        return new MemcachedCachePool($this->getDb());
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
     * Get a lock instance.
     *
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null) {
        return new MemcachedLock($this->getDb(), $this->prefix . $name, $seconds, $owner);
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
