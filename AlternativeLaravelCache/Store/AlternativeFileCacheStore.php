<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
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
     * @return AbstractCachePool|TaggableCacheItemPoolInterface
     */
    public function wrapConnection() {
        return new FilesystemCachePool($this->getDb());
    }

    public function setPrefix(string $prefix): void {
        // allowed chars: "a-zA-Z0-9_.! "
        parent::setPrefix(preg_replace('%[^a-zA-Z0-9_.! ]+%', '_', $prefix));
    }

    public function fixItemKey(string $key): string {
        // allowed chars: "a-zA-Z0-9_.! "
        return parent::fixItemKey(preg_replace(
            ['%-+%',   '%\|+%',  '%/+%', '%[^a-zA-Z0-9_.! ]+%'],
            ['_dash_', '_pipe_', '_ds_', '_'],
            $key
        ));
    }
}
