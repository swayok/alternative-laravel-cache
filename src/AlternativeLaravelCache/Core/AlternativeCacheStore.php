<?php

namespace AlternativeLaravelCache\Core;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\CacheItem;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Store;

abstract class AlternativeCacheStore extends TaggableStore implements Store {

    /**
     * The database connection.
     *
     * @var mixed
     */
    protected $db;

    /**
     * @var string
     */
    public $hierarchySeparator;

    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;

    /**
     * The Redis connection that should be used.
     *
     * @var string
     */
    protected $connection;

    /**
     * Wrapped connection (see http://www.php-cache.com/)
     *
     * @var AbstractCachePool|HierarchicalPoolInterface|TaggableCacheItemPoolInterface
     */
    protected $wrappedConnection = null;

    /**
     * Tags list. Used only for 1 operation (put, putMany, flush, forever)
     *
     * @var null|array
     */
    protected $tags;

    /**
     * Autodetected in isDurationInSeconds() depending on Laravel/Lumen version
     * @var bool
     */
    private $isDurationInSeconds;

    /**
     * @param mixed $db - something like \Illuminate\Redis\Database
     * @param string $prefix
     * @param string|null $connection - connection name to use (if applicable)
     */
    public function __construct($db, $prefix, $connection = null) {
        $this->db = $db;
        $this->connection = $connection;
        $this->setPrefix($prefix);
    }

    /**
     * Get the Redis database instance.
     *
     * @return mixed
     */
    public function getDb() {
        return $this->db;
    }

    /**
     * Set the connection name to be used.
     *
     * @param  string $connection
     * @return void
     */
    public function setConnection($connection) {
        $this->connection = $connection;
    }

    /**
     * Wraps DB connection with wrapper from http://www.php-cache.com/
     *
     * @return AbstractCachePool|HierarchicalPoolInterface|TaggableCacheItemPoolInterface
     */
    abstract public function wrapConnection();

    /**
     * Get the connection client wrapper
     * All wrappers listed here: http://www.php-cache.com/
     *
     * @return AbstractCachePool|HierarchicalPoolInterface|TaggableCacheItemPoolInterface
     */
    public function getWrappedConnection() {
        if ($this->wrappedConnection === null) {
            $this->wrappedConnection = $this->wrapConnection();
        }
        return $this->wrappedConnection;
    }

    public function getHierarchySeparator() {
        if ($this->hierarchySeparator) {
            return $this->hierarchySeparator;
        }
        $this->hierarchySeparator = '_';
        if ($this->getWrappedConnection() instanceof HierarchicalPoolInterface) {
            $this->hierarchySeparator = HierarchicalPoolInterface::HIERARCHY_SEPARATOR;
        }
        return $this->hierarchySeparator;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array $key
     * @return mixed
     */
    public function get($key) {
        $this->_pullTags();
        $item = $this->getWrappedConnection()->getItem($this->itemKey($key));
        return $this->decodeItem($item);
    }

    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param  array $keys
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function many(array $keys) {
        $this->_pullTags();
        $items = $this->getWrappedConnection()->getItems(array_map([$this, 'itemKey'], $keys));
        return array_map([$this, 'decodeItem'], $items);
    }

    /**
     * Store an item in the cache for a given number of minutes/seconds.
     *
     * @param  string $key
     * @param  mixed $value
     * @param  int $duration - seconds for Laravel >= 5.8 or minutes for Laravel <= 5.7
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function put($key, $value, $duration) {
        return $this->getWrappedConnection()->save($this->newItem($key, $value, $this->_pullTags(), $duration));
    }

    /**
     * Store multiple items in the cache for a given number of minutes.
     *
     * @param  array $values
     * @param  int $duration - seconds for Laravel >= 5.8 or minutes for Laravel <= 5.7
     * @return void
     */
    public function putMany(array $values, $duration) {
        if (!count($values)) {
            return;
        }
        $tags = $this->_pullTags();
        foreach ($values as $key => $value) {
            $this->getWrappedConnection()->saveDeferred($this->newItem($key, $value, $tags));
        }
        return $this->getWrappedConnection()->commit();
    }

    /**
     * Increment the value of an item in the cache.
     * Note: be careful implementing database native increment - real $key in db may be not
     * the same as $this->itemKey($key)
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function increment($key, $value = 1) {
        $this->_pullTags();
        $item = $this->getWrappedConnection()->getItem($this->itemKey($key));
        if ($item->isHit()) {
            $item->set((int) $item->get() + (int) $value);
            $this->getWrappedConnection()->save($item);
            return $item->get();
        }
        return false;
    }

    /**
     * Decrement the value of an item in the cache.
     * Note: be careful implementing database native increment - real $key in db may be not
     * the same as $this->itemKey($key)
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function decrement($key, $value = 1) {
        $this->_pullTags();
        $item = $this->getWrappedConnection()->getItem($this->itemKey($key));
        if ($item->isHit()) {
            $item->set((int) $item->get() - (int) $value);
            $this->getWrappedConnection()->save($item);
            return $item->get();
        }
        return false;
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string $key
     * @param  mixed $value
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forever($key, $value) {
        return $this->getWrappedConnection()->save($this->newItem($key, $value, $this->_pullTags()));
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string $key
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forget($key) {
        $this->_pullTags();
        return (bool)$this->getWrappedConnection()->deleteItem($this->itemKey($key));
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush() {
        $tags = $this->_pullTags();
        if (empty($tags)) {
            return (bool)$this->getWrappedConnection()->clear();
        } else {
            return (bool)$this->getWrappedConnection()->clearTags($tags);
        }
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function clear() {
        return $this->flush();
    }

    /**
     * Set the cache key prefix.
     *
     * @param  string $prefix
     * @return void
     */
    public function setPrefix($prefix) {
        $separator = $this->getHierarchySeparator();
        $this->prefix = $separator . (!empty($prefix) ? $prefix . $separator : '');
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix() {
        return $this->prefix;
    }

    /**
     * @param CacheItem $item
     * @return mixed
     */
    protected function decodeItem($item) {
        if ($item->isHit()) {
            return $item->get();
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return int|string
     */
    protected function encodeValue($value) {
        return $value;
    }

    /**
     * @return bool
     */
    protected function isDurationInSeconds() {
        if ($this->isDurationInSeconds === null) {
            $this->isDurationInSeconds = preg_match('%^.*?(\d+\.\d+)%', app()->version(), $matches) && (float)$matches[1] >= 5.8;
        }
        return $this->isDurationInSeconds;
    }

    /**
     * @return int
     */
    protected function getDefaultDuration() {
        return 525600 * ($this->isDurationInSeconds() ? 60 : 1);
    }

    /**
     * @return int
     */
    protected function getDurationMultiplier() {
        return $this->isDurationInSeconds() ? 1 : 60;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param $tags
     * @param int $duration
     * @return CacheItem
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function newItem($key, $value, $tags = null, $duration = null) {
        $duration = $duration === null ? $this->getDefaultDuration() : max(1, (int)$duration);
        $item = $this->getWrappedConnection()->getItem($this->itemKey($key));
        $item
            ->set($this->encodeValue($value))
            ->expiresAfter($duration * $this->getDurationMultiplier());
        if (!empty($tags)) {
            $item->setTags($tags);
        }
        return $item;
    }

    /**
     * Convert user-friendly key to a real key in storage
     *
     * @param $key
     * @return string
     */
    public function itemKey($key) {
        return $this->prefix . $this->fixItemKey($key);
    }

    /**
     * Fix original item key to be compatible with cache storeage wrapper.
     * Used in some stores to fix not allowed chars usage in key name
     *
     * @param $key
     * @return mixed
     */
    public function fixItemKey($key) {
        return ltrim($key, $this->getHierarchySeparator());
    }

    /**
     * Set tags list for a single operation.
     * Note: any get/save/delete operation will reset tags list even it is not using them
     *
     * @param array $tags
     * @return $this
     */
    public function _setTagsForNextOperation(array $tags) {
        $this->tags = $tags;
        return $this;
    }

    /**
     * @return null|array
     */
    public function _pullTags() {
        if ($this->tags !== null) {
            $tags = $this->tags;
            $this->tags = null;
            return $tags;
        }
        return null;
    }

    /**
     * Begin executing a new tags operation.
     *
     * @param  array|mixed $names
     * @return AlternativeTaggedCache
     * @throws \InvalidArgumentException
     */
    public function tags($names) {
        if (!empty($names)) {
            if (is_string($names)) {
                $names = [$names];
            }
            if (!is_array($names)) {
                throw new \InvalidArgumentException('$names argument should be null, array or string');
            }
        }
        $tags = new AlternativeTagSet($this, is_array($names) ? $names : func_get_args());
        return new AlternativeTaggedCache($this, $this->getPrefix(), $tags);
    }

}
