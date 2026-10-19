<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Pool\HierarchicalFilesystemCachePool;
use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalPoolInterface;
use AlternativeLaravelCache\Vendors\TagInterop\TaggableCacheItemPoolInterface;
use Illuminate\Filesystem\Filesystem;

class AlternativeHierarchicalFileCacheStore extends AlternativeCacheStore
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
     * Permissions for created cache directories.
     *
     * @var int
     */
    protected $directoryPermission;

    /**
     * @param Filesystem $db
     * @param string $prefix
     * @param string $directory
     * @param int|null $filePermission
     * @param int $directoryPermission
     */
    public function __construct(
        $db,
        $prefix,
        $directory,
        $filePermission = null,
        $directoryPermission = 0755
    ) {
        parent::__construct($db, $prefix);
        $this->directory = $directory;
        $this->filePermission = $filePermission;
        $this->directoryPermission = $directoryPermission;
    }

    /**
     * Wraps DB connection with wrapper from http://www.php-cache.com/
     *
     * @return AbstractCachePool|HierarchicalPoolInterface|TaggableCacheItemPoolInterface
     */
    public function wrapConnection()
    {
        return new HierarchicalFilesystemCachePool(
            $this->getDb(),
            $this->getDirectory(),
            $this->filePermission,
            $this->directoryPermission
        );
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

    /**
     * Get the working directory of the cache.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    public function setPrefix(string $prefix): void
    {
        // allowed chars: "a-zA-Z0-9_.! "
        parent::setPrefix(preg_replace('%[^a-zA-Z0-9_.! ]+%', '_', $prefix));
    }

    public function fixItemKey(string $key): string
    {
        // allowed chars: "a-zA-Z0-9_.! |"
        // note: do not replace pipe "|" or hierarchical cache won't work
        // note: directory separator "/" will be converted to pipe "|" in order to provide
        // more native way of folding like "/folder/subfolder/item/id"
        return parent::fixItemKey(
            preg_replace(
                ['%-+%', '%[/|]+%', '%[^a-zA-Z0-9_.! |]+%'],
                ['_dash_', '|', '_'],
                $key
            )
        );
    }

    public function getHierarchySeparator(): string
    {
        return HierarchicalPoolInterface::HIERARCHY_SEPARATOR;
    }
}
