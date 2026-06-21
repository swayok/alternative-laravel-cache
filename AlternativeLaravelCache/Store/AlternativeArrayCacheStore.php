<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use AlternativeLaravelCache\Core\AlternativeCacheStore;
use AlternativeLaravelCache\Vendors\Array\ArrayCachePool;
use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalPoolInterface;
use AlternativeLaravelCache\Vendors\TagInterop\TaggableCacheItemPoolInterface;

class AlternativeArrayCacheStore extends AlternativeCacheStore
{
    /**
     * Indicates if values are serialized within the store.
     *
     * @var bool
     */
    protected bool $serializesValues;

    /**
     * The classes that should be allowed during unserialization.
     *
     * @var array|bool|null
     */
    protected array | bool | null $serializableClasses;

    /**
     * Create a new Array store.
     *
     * @param bool $serializesValues
     * @param array|bool|null $serializableClasses
     */
    public function __construct(bool $serialize = false, array | bool | null $serializableClasses = null)
    {
        parent::__construct(null, '');
        $this->serializesValues = $serialize;
        $this->serializableClasses = $serializableClasses;
    }

    /**
     * Wraps DB connection with wrapper from http://www.php-cache.com/
     *
     * @return AbstractCachePool|HierarchicalPoolInterface|TaggableCacheItemPoolInterface
     */
    public function wrapConnection()
    {
        return new ArrayCachePool();
    }

    public function setPrefix(string $prefix): void
    {
        // Array store should not use prefix.
        parent::setPrefix('');
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
