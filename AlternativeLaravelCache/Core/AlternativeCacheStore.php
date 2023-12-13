<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Core;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\CacheItem;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Illuminate\Cache\TaggableStore;
use Illuminate\Cache\TaggedCache;
use Psr\Log\LoggerInterface;

abstract class AlternativeCacheStore extends TaggableStore
{
    /**
     * The database connection.
     *
     * @var mixed
     */
    protected $db;

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
     * Wrapped connection (see http://www.php-cache.com/).
     *
     * @var AbstractCachePool|HierarchicalPoolInterface|TaggableCacheItemPoolInterface
     */
    protected $wrappedConnection;

    /**
     * Logger for wrapped connection.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Tags list. Used only for 1 operation (put, putMany, flush, forever).
     *
     * @var null|array
     */
    protected $tags;

    /**
     * @param mixed $db - something like \Illuminate\Redis\Database
     * @param string $prefix
     * @param string|null $connection - connection name to use (if applicable)
     */
    public function __construct($db, $prefix, $connection = null)
    {
        $this->db = $db;
        $this->setConnection($connection);
        $this->setPrefix($prefix);
    }

    /**
     * Get the Redis database instance.
     *
     * @return mixed
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Set the connection name to be used.
     */
    public function setConnection(?string $connection): void
    {
        $this->connection = $connection;
        $this->wrappedConnection = null;
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
    public function getWrappedConnection()
    {
        if ($this->wrappedConnection === null) {
            $this->wrappedConnection = $this->wrapConnection();
            if ($this->logger) {
                $this->wrappedConnection->setLogger($this->logger);
            }
        }
        return $this->wrappedConnection;
    }

    /**
     * Set logger for wrapped connection.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        if ($this->wrappedConnection) {
            $this->wrappedConnection->setLogger($this->logger);
        }
    }

    public function getHierarchySeparator(): string
    {
        return '_';
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string|array $key
     *
     * @return mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function get($key)
    {
        if (is_array($key)) {
            return $this->many($key);
        }
        $this->_pullTags();
        $item = $this->getWrappedConnection()->getItem($this->itemKey($key));
        return $this->decodeItem($item);
    }

    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param array $keys
     *
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function many(array $keys)
    {
        $this->_pullTags();
        $items = $this->getWrappedConnection()->getItems(array_map([$this, 'itemKey'], $keys));
        return array_map([$this, 'decodeItem'], $items);
    }

    /**
     * Store an item in the cache for a given number of minutes/seconds.
     *
     * @param string|\Stringable $key
     * @param mixed $value
     * @param \DateTimeInterface|\DateInterval|int|null $ttl - int: seconds
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function put($key, $value, $ttl)
    {
        return $this->getWrappedConnection()->save(
            $this->newItem($key, $value, $this->_pullTags(), $ttl)
        );
    }

    /**
     * Store multiple items in the cache for a given number of minutes.
     *
     * @param array $values - ['cache_key' => $cacheValue]
     * @param \DateTimeInterface|\DateInterval|int|null $ttl - int: seconds
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function putMany(array $values, $ttl)
    {
        if (!count($values)) {
            return false;
        }
        $tags = $this->_pullTags();
        foreach ($values as $key => $value) {
            $this->getWrappedConnection()->saveDeferred($this->newItem($key, $value, $tags, $ttl));
        }
        return $this->getWrappedConnection()->commit();
    }

    /**
     * Increment the value of an item in the cache.
     * Note: be careful implementing database native increment - real $key in db may be not
     * the same as $this->itemKey($key)
     *
     * @param string|\Stringable $key
     * @param int $value
     *
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        $this->_pullTags();
        $item = $this->getWrappedConnection()->getItem($this->itemKey($key));
        if ($item->isHit()) {
            $item->set((int)$item->get() + (int)$value);
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
     * @param string|\Stringable $key
     * @param int $value
     *
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        $this->_pullTags();
        $item = $this->getWrappedConnection()->getItem($this->itemKey($key));
        if ($item->isHit()) {
            $item->set((int)$item->get() - (int)$value);
            $this->getWrappedConnection()->save($item);
            return $item->get();
        }
        return false;
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string|\Stringable $key
     * @param mixed $value
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forever($key, $value)
    {
        return $this->getWrappedConnection()->save($this->newItem($key, $value, $this->_pullTags()));
    }

    /**
     * Remove an item from the cache.
     *
     * @param string|\Stringable $key
     *
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function forget($key)
    {
        $this->_pullTags();
        return (bool)$this->getWrappedConnection()->deleteItem($this->itemKey($key));
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $tags = $this->_pullTags();
        if (empty($tags)) {
            return (bool)$this->getWrappedConnection()->clear();
        }

        /** @noinspection PhpUndefinedMethodInspection */
        return (bool)$this->getWrappedConnection()->clearTags($tags);
    }

    /**
     * Remove all items from the cache.
     */
    public function clear(): bool
    {
        return $this->flush();
    }

    /**
     * Set the cache key prefix.
     */
    public function setPrefix(string $prefix): void
    {
        $separator = $this->getHierarchySeparator();
        $this->prefix = $separator . (!empty($prefix) ? $prefix . $separator : '');
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return mixed
     */
    protected function decodeItem(PhpCacheItem $item)
    {
        if ($item->isHit()) {
            return $item->get();
        }
        return null;
    }

    /**
     * @param mixed $value
     *
     * @return int|string
     */
    protected function encodeValue($value)
    {
        return $value;
    }

    protected function getDefaultDuration(): int
    {
        return 31536000; //< 365 days
    }

    /**
     * @param string|\Stringable $key
     * @param mixed $value
     * @param array|null $tags
     * @param \DateTimeInterface|\DateInterval|int|null $ttl - int: seconds
     *
     * @return CacheItem
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function newItem($key, $value, ?array $tags = null, $ttl = null)
    {
        $ttl = $ttl === null ? $this->getDefaultDuration() : max(1, (int)$ttl);
        $item = $this->getWrappedConnection()->getItem($this->itemKey($key));
        $item
            ->set($this->encodeValue($value))
            ->expiresAfter($ttl);
        if (!empty($tags)) {
            $item->setTags($tags);
        }
        return $item;
    }

    /**
     * Convert user-friendly key to a real key in storage
     *
     * @param string|\Stringable $key
     */
    public function itemKey($key): string
    {
        return $this->prefix . $this->fixItemKey((string)$key);
    }

    /**
     * Fix original item key to be compatible with cache storeage wrapper.
     * Used in some stores to fix not allowed chars usage in key name
     */
    public function fixItemKey(string $key): string
    {
        return ltrim($key, $this->getHierarchySeparator());
    }

    /**
     * Set tags list for a single operation.
     * Note: any get/save/delete operation will reset tags list even it is not using them
     *
     * @return $this
     */
    public function _setTagsForNextOperation(array $tags)
    {
        $this->tags = $tags;
        return $this;
    }

    public function _pullTags(): ?array
    {
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
     * @param array|string $names
     *
     * @return AlternativeTaggedCache|TaggedCache
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function tags($names)
    {
        if (is_string($names)) {
            $names = [$names];
        }
        if (!is_array($names)) {
            throw new \Cache\Adapter\Common\Exception\InvalidArgumentException(
                '$names argument should be array or string'
            );
        }
        $tags = new AlternativeTagSet($this, $names);
        return new AlternativeTaggedCache($this, $this->getPrefix(), $tags);
    }
}
