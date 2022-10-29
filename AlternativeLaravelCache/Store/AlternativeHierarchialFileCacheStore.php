<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Pool\HierarchialFilesystemCachePoolFlysystem1;
use AlternativeLaravelCache\Pool\HierarchialFilesystemCachePoolFlysystem3;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use League\Flysystem\Filesystem;

class AlternativeHierarchialFileCacheStore extends AlternativeCacheStore {

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
    public function wrapConnection() {
        if (class_exists('League\Flysystem\Adapter\Local')) {
            return new HierarchialFilesystemCachePoolFlysystem1($this->getDb());
        } else {
            return new HierarchialFilesystemCachePoolFlysystem3($this->getDb());
        }
    }

    public function getHierarchySeparator() {
       return HierarchicalPoolInterface::HIERARCHY_SEPARATOR;
    }

    public function setPrefix($prefix) {
        // allowed chars: "a-zA-Z0-9_.! "
        parent::setPrefix(preg_replace('%[^a-zA-Z0-9_\.! ]+%', '_', $prefix));
    }

    public function fixItemKey($key) {
        // allowed chars: "a-zA-Z0-9_.! |"
        // note: do not replace pipe "|" or hierarachial cache won't work
        // note: directory separator "/" will be converted to pipe "|" in order to provide
        // more native way of folding like "/folder/subfolder/item/id"
        return parent::fixItemKey(preg_replace(
            ['%-+%',   '%[/|]+%', '%[^a-zA-Z0-9_\.! |]+%'],
            ['_dash_', '|', '_'],
            $key
        ));
    }

}
