<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\FileSystem\FilesystemCachePool;
use AlternativeLaravelCache\Vendors\TagInterop\TaggableCacheItemPoolInterface;
use League\Flysystem\Filesystem;

class AlternativeFileCacheStore extends AlternativeCacheStore
{

    /**
     * The Illuminate Filesystem instance.
     *
     * @var Filesystem
     */
    protected $db;

    /**
     * The file cache directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * Octal representation of the cache file permissions.
     *
     * @var int|null
     */
    protected $filePermission;

    /**
     * The classes that should be allowed during unserialization.
     *
     * @var array|bool|null
     */
    protected array | bool | null $serializableClasses = true;

    /**
     * Create a new file cache store instance.
     *
     * @param \League\Flysystem\Filesystem $db
     * @param string $prefix
     * @param string $directory
     * @param int|null $filePermission
     * @param array|bool|null $serializableClasses
     */
    public function __construct(
        $db,
        $prefix,
        $directory,
        $filePermission = null,
        $serializableClasses = true
    ) {
        parent::__construct($db, $prefix);
        $this->directory = $directory ?? 'cache';
        $this->filePermission = $filePermission;
        $this->serializableClasses = $serializableClasses;
    }

    /**
     * Wraps DB connection with wrapper from http://www.php-cache.com/
     *
     * @return AbstractCachePool|TaggableCacheItemPoolInterface
     */
    public function wrapConnection()
    {
        return new FilesystemCachePool($this->getDb(), $this->getDirectory());
    }

    public function setPrefix(string $prefix): void
    {
        // allowed chars: "a-zA-Z0-9_.! "
        parent::setPrefix(preg_replace('%[^a-zA-Z0-9_.! ]+%', '_', $prefix));
    }

    public function fixItemKey(string $key): string
    {
        // allowed chars: "a-zA-Z0-9_.! "
        return parent::fixItemKey(
            preg_replace(
                ['%-+%', '%\|+%', '%/+%', '%[^a-zA-Z0-9_.! ]+%'],
                ['_dash_', '_pipe_', '_ds_', '_'],
                $key
            )
        );
    }

    /**
     * Get the working directory of the cache.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory ?? 'cache';
    }

    /**
     * Set the working directory of the cache.
     *
     * @param string $directory
     *
     * @return $this
     */
    public function setDirectory($directory)
    {
        $this->directory = $directory;

        return $this;
    }

}
