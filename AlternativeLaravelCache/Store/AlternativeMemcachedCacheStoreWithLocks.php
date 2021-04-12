<?php

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\MemcachedLock;

class AlternativeMemcachedCacheStoreWithLocks extends AlternativeMemcachedCacheStore {
    
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
