<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\MemcachedLock;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;

class AlternativeMemcachedCacheStoreWithLocks extends AlternativeMemcachedCacheStore implements LockProvider
{
    /**
     * Get a lock instance.
     */
    public function lock($name, $seconds = 0, $owner = null): Lock
    {
        return new MemcachedLock($this->getDb(), $this->prefix . $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     */
    public function restoreLock($name, $owner): Lock
    {
        return $this->lock($name, 0, $owner);
    }
}
