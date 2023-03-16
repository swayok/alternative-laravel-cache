<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\MemcachedLock;
use Illuminate\Contracts\Cache\Lock;

class AlternativeMemcachedCacheStoreWithLocks extends AlternativeMemcachedCacheStore
{
    /**
     * Get a lock instance.
     */
    public function lock(string $name, int $seconds = 0, ?string $owner = null): Lock
    {
        return new MemcachedLock($this->getDb(), $this->prefix . $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     */
    public function restoreLock(string $name, string $owner): Lock
    {
        return $this->lock($name, 0, $owner);
    }
}
