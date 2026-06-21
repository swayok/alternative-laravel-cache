<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\ArrayLock;
use Illuminate\Contracts\Cache\CanFlushLocks;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Carbon;

class AlternativeArrayCacheStoreWithLocks extends AlternativeArrayCacheStore implements LockProvider, CanFlushLocks
{
    /**
     * The array of locks.
     *
     * @var array<string, array{owner: ?string, expiresAt: ?\Illuminate\Support\Carbon}>
     */
    public $locks = [];

    /**
     * Get a lock instance.
     *
     * @param  string  $name
     * @param  int  $seconds
     * @param  string|null  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function lock($name, $seconds = 0, $owner = null)
    {
        /** @noinspection PhpParamsInspection */
        return new ArrayLock($this, $name, $seconds, $owner);
    }

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param  string  $name
     * @param  string  $owner
     * @return \Illuminate\Contracts\Cache\Lock
     */
    public function restoreLock($name, $owner)
    {
        return $this->lock($name, 0, $owner);
    }

    /**
     * Remove all locks from the store.
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function flushLocks(): bool
    {
        if (! $this->hasSeparateLockStore()) {
            throw new \RuntimeException('Flushing locks is only supported when the lock store is separate from the cache store.');
        }

        $this->locks = [];

        return true;
    }

    /**
     * Determine if the lock store is separate from the cache store.
     *
     * @return bool
     */
    public function hasSeparateLockStore(): bool
    {
        return true;
    }
}
