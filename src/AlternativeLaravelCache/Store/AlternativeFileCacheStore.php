<?php

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\Taggable\TaggablePoolInterface;
use League\Flysystem\Filesystem;

class AlternativeFileCacheStore extends AlternativeCacheStore {

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
        return new FilesystemCachePool($this->getDb());
    }

    public function itemKey($key) {
        // allowed chars: a-zA-Z0-9_\.!
        return parent::itemKey(preg_replace(
            ['%-+%',   '%\|+%',  '%/+%', '%[^a-zA-Z0-9_\.! ]+%'],
            ['_dash_', '_pipe_', '_ds_', '_'],
            $key
        ));
    }

}