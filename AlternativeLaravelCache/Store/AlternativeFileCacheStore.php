<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\FileSystem\FilesystemCachePool;
use AlternativeLaravelCache\Vendors\TagInterop\TaggableCacheItemPoolInterface;
use Illuminate\Filesystem\Filesystem;

class AlternativeFileCacheStore extends AlternativeCacheStore
{

    /**
     * The Illuminate Filesystem instance.
     *
     * @var Filesystem
     */
    protected $db;

    /**
     * Permissions for created cache directories.
     *
     * @var int
     */
    protected $directoryPermission = 0755;

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
     * Classes/interfaces whose subclasses are allowed during unserialization.
     *
     * @var string[]
     */
    protected array $serializableBaseClasses = [];

    /**
     * Create a new file cache store instance.
     *
     * @param Filesystem $db
     * @param string $prefix
     * @param string $directory
     * @param int|null $filePermission
     * @param array|bool|null $serializableClasses
     * @param int $directoryPermission
     * @param string[] $serializableBaseClasses
     */
    public function __construct(
        $db,
        $prefix,
        $directory,
        $filePermission = null,
        $serializableClasses = true,
        $directoryPermission = 0755,
        array $serializableBaseClasses = []
    ) {
        parent::__construct($db, $prefix);
        $this->directory = $directory ?? 'cache';
        $this->filePermission = $filePermission;
        $this->serializableClasses = $serializableClasses;
        $this->directoryPermission = $directoryPermission;
        $this->serializableBaseClasses = $serializableBaseClasses;
    }

    /**
     * Wraps DB connection with wrapper from http://www.php-cache.com/
     *
     * @return AbstractCachePool|TaggableCacheItemPoolInterface
     */
    public function wrapConnection()
    {
        $pool = new FilesystemCachePool(
            $this->getDb(),
            $this->getDirectory(),
            $this->filePermission,
            $this->directoryPermission
        );
        $pool->setSerializableClasses($this->serializableClasses);
        $pool->setSerializableBaseClasses($this->serializableBaseClasses);

        return $pool;
    }

    /**
     * Get the Filesystem instance.
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->getDb();
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
