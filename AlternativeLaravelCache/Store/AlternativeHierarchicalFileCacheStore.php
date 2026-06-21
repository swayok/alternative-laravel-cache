<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Pool\HierarchicalFilesystemCachePoolFlysystem1;
use AlternativeLaravelCache\Pool\HierarchicalFilesystemCachePoolFlysystem3;
use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalPoolInterface;
use AlternativeLaravelCache\Vendors\TagInterop\TaggableCacheItemPoolInterface;
use League\Flysystem\Filesystem;

class AlternativeHierarchicalFileCacheStore extends AlternativeCacheStore
{
    /**
     * The Illuminate Filesystem instance.
     *
     * @var Filesystem
     */
    protected $db;

    /**
     * Wraps DB connection with wrapper from http://www.php-cache.com/
     *
     * @return AbstractCachePool|HierarchicalPoolInterface|TaggableCacheItemPoolInterface
     */
    public function wrapConnection()
    {
        if (class_exists('League\Flysystem\Adapter\Local')) {
            return new HierarchicalFilesystemCachePoolFlysystem1($this->getDb());
        }

        return new HierarchicalFilesystemCachePoolFlysystem3($this->getDb());
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
