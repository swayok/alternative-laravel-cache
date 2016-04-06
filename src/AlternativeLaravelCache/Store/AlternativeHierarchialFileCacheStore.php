<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Pool\HierarchialFilesystemCachePool;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\Taggable\TaggablePoolInterface;
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
     * @return AbstractCachePool|HierarchicalPoolInterface|TaggablePoolInterface
     */
    public function wrapConnection() {
        return new HierarchialFilesystemCachePool($this->getDb());
    }

    public function fixItemKey($key) {
        // allowed chars: "a-zA-Z0-9_\.! |"
        return parent::fixItemKey(preg_replace(
            ['%-+%',   '%/+%', '%[^a-zA-Z0-9_\.! |]+%'],
            ['_dash_', '_ds_', '_'],
            $key
        ));
    }

}