<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\FileLock;
use Illuminate\Contracts\Cache\LockProvider;
use Symfony\Component\Finder\Finder;

class AlternativeFileCacheStoreWithLocks extends AlternativeFileCacheStore implements LockProvider
{
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
        return new FileLock(
            new static(
                $this->db,
                $this->prefix,
                $this->lockDirectory ?? $this->directory,
                $this->filePermission,
                $this->serializableClasses,
                $this->directoryPermission
            ),
            "file-store-lock:{$name}",
            $seconds,
            $owner
        );
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
     * The file cache lock directory.
     *
     * @var string|null
     */
    protected $lockDirectory;

    /**
     * Set the cache directory where locks should be stored.
     *
     * @param  string|null  $lockDirectory
     * @return $this
     */
    public function setLockDirectory($lockDirectory)
    {
        $this->lockDirectory = $lockDirectory;

        return $this;
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

        if (! $this->db->isDirectory($this->lockDirectory)) {
            return false;
        }

        $subDirectories = Finder::create()
            ->in($this->lockDirectory)
            ->directories()
            ->depth(0)
            ->sortByName();
        foreach ($subDirectories as $lockDirectory) {
            $this->db->deleteDirectory($lockDirectory->getPathname());

            if ($this->db->isDirectory($lockDirectory->getPathname())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the lock store is separate from the cache store.
     *
     * @return bool
     */
    public function hasSeparateLockStore(): bool
    {
        return $this->lockDirectory !== null && $this->lockDirectory !== $this->directory;
    }
}
