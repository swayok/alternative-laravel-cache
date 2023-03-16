<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Core;

use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Store;

class AlternativeTaggedCache extends TaggedCache
{
    /**
     * The tag set instance.
     *
     * @var AlternativeTagSet
     */
    protected $tags;

    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Create a new tagged cache instance.
     *
     * @param Store             $store
     * @param string            $prefix
     * @param AlternativeTagSet $tags
     */
    public function __construct(Store $store, string $prefix, AlternativeTagSet $tags)
    {
        parent::__construct($store, $tags);
        $this->setPrefix($prefix);
    }

    /**
     * Store an item in the cache.
     *
     * @param string                                    $key
     * @param mixed                                     $value
     * @param \DateTimeInterface|\DateInterval|int|null $ttl - int: seconds
     * @return bool
     */
    public function put($key, $value, $ttl = null): bool
    {
        $this->sendTagsToStore();
        return parent::put($key, $value, $ttl);
    }

    /**
     * Store multiple items in the cache for a given number of minutes.
     *
     * @param array $values
     * @param int   $ttl - seconds for Laravel >= 5.8 or minutes for Laravel <= 5.7
     * @return bool
     */
    public function putMany(array $values, $ttl = null): bool
    {
        $this->sendTagsToStore();
        return parent::putMany($values, $ttl);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public function forever($key, $value): bool
    {
        $this->sendTagsToStore();
        return parent::forever($key, $value);
    }

    /**
     * @return AlternativeCacheStore
     */
    protected function sendTagsToStore(): AlternativeCacheStore
    {
        /** @var AlternativeCacheStore $store */
        $store = $this->getStore();
        return $store->_setTagsForNextOperation($this->tags->getKeys());
    }

    /**
     * Needed to prevent laravel's tag key modification
     *
     * @param string $key
     * @return string
     */
    protected function itemKey($key): string
    {
        return $key;
    }

    /**
     * @throws \BadMethodCallException
     */
    public function taggedItemKey($key)
    {
        throw new \BadMethodCallException('Method taggedItemKey() is not used in AlternativeTaggedCache');
    }

    /**
     * Set the cache key prefix.
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

}
