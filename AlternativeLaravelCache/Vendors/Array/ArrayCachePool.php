<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AlternativeLaravelCache\Vendors\Array;

use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Common\CacheItem;
use AlternativeLaravelCache\Vendors\Common\PhpCacheItem;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalCachePoolTrait;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalPoolInterface;

/**
 * Array cache pool. You could set a limit of how many items you want to be stored to avoid memory leaks.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ArrayCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    /**
     * @type PhpCacheItem[]
     */
    private array $cache = [];

    /**
     * @type array A map to hold keys
     */
    private array $keyMap = [];

    /**
     * @type int The maximum number of keys in the map
     */
    private ?int $limit = null;

    /**
     * @type int The next key that we should remove from the cache
     */
    private int $currentPosition = 0;

    /**
     * @param int $limit the amount if items stored in the cache. Using a limit will reduce memory leaks.
     * @param array $cache
     */
    public function __construct(?int $limit = null, array $cache = [])
    {
        $this->cache = $cache;
        $this->limit = $limit;
    }

    /**
     * Get all cache.
     * @return array
     */
    public function getCache(): array
    {
        return $this->cache;
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemWithoutGenerateCacheKey($key)
    {
        if (isset($this->deferred[$key])) {
            /** @type CacheItem $item */
            $item = clone $this->deferred[$key];
            $item->moveTagsToPrevious();

            return $item;
        }

        return $this->fetchObjectFromCache($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        $keys = $this->getHierarchyKey($key);

        if (!$this->cacheIsset($keys)) {
            return [false, null, [], null];
        }

        /** @var array $element */
        $element = $this->cacheToolkit($keys);
        [$data, $tags, $timestamp] = $element;

        if (is_object($data)) {
            $data = clone $data;
        }

        return [true, $data, $tags, $timestamp];
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        $this->cache = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $this->commit();
        $keys = $this->getHierarchyKey($key);

        $this->clearHierarchyKeyCache();
        $this->cacheToolkit($keys, null, true);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(PhpCacheItem $item, $ttl)
    {
        $keys = $this->getHierarchyKey($item->getKey());
        $value = $item->get();
        if (is_object($value)) {
            $value = clone $value;
        }
        $this->cacheToolkit($keys, [$value, $item->getTags(), $item->getExpirationTimestamp()]);

        if ($this->limit !== null) {
            // Remove the oldest value
            if (isset($this->keyMap[$this->currentPosition])) {
                unset($this->cache[$this->keyMap[$this->currentPosition]]);
            }

            // Add the new key to the current position
            $this->keyMap[$this->currentPosition] = implode(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, $keys);

            // Increase the current position
            $this->currentPosition = ($this->currentPosition + 1) % $this->limit;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    protected function getDirectValue($key)
    {
        return isset($this->cache[$key]) ? $this->cache[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        if (!isset($this->cache[$name])) {
            $this->cache[$name] = [];
        }

        return $this->cache[$name];
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        unset($this->cache[$name]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $key)
    {
        $this->cache[$name][] = $key;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeListItem($name, $key)
    {
        if (isset($this->cache[$name])) {
            foreach ($this->cache[$name] as $i => $item) {
                if ($item === $key) {
                    unset($this->cache[$name][$i]);
                }
            }
        }
    }

    /**
     * Used to manipulate cached data by extracting, inserting or deleting value.
     *
     * @param array $keys
     * @param null|mixed $value
     * @param bool $unset
     *
     * @return mixed
     */
    private function cacheToolkit($keys, $value = null, $unset = false)
    {
        $element = &$this->cache;

        while ($keys && ($key = array_shift($keys))) {
            if (!$keys && is_null($value) && $unset) {
                unset($element[$key]);
                unset($element);
                $element = null;
            } else {
                $element =& $element[$key];
            }
        }

        if (!$unset && !is_null($value)) {
            $element = $value;
        }

        return $element;
    }

    /**
     * Checking if given keys exists and is valid.
     *
     * @param array $keys
     *
     * @return bool
     */
    private function cacheIsset($keys)
    {
        $has = false;
        $array = $this->cache;

        foreach ($keys as $key) {
            $has = (
                $array !== null
                && array_key_exists($key, $array)
            );
            if ($has) {
                $array = $array[$key];
            }
        }

        if (is_array($array)) {
            $has = $has && array_key_exists(0, $array);
        }

        return $has;
    }

    /**
     * Get a key to use with the hierarchy. If the key does not start with HierarchicalPoolInterface::SEPARATOR
     * this will return an unalterered key. This function supports a tagged key. Ie "foo:bar".
     * With this overwrite we'll return array as keys.
     *
     * @param string $key The original key
     *
     * @return array
     */
    protected function getHierarchyKey($key)
    {
        if (!$this->isHierarchyKey($key)) {
            return [$key];
        }

        return $this->explodeKey($key);
    }
}
