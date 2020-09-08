<?php

namespace AlternativeLaravelCache\Core;

use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Store;

class AlternativeTaggedCache extends TaggedCache {

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
     * @param  \Illuminate\Contracts\Cache\Store $store
     * @param  string $prefix
     * @param  AlternativeTagSet $tags
     */
    public function __construct(Store $store, $prefix, AlternativeTagSet $tags) {
        parent::__construct($store, $tags);
        $this->setPrefix($prefix);
    }

    /**
     * Store an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @param  \DateTime|int $ttl - seconds for Laravel >= 5.8 or minutes for Laravel <= 5.7
     * @return void
     */
    public function put($key, $value, $ttl = null) {
        $this->sendTagsToStore();
        return parent::put($key, $value, $ttl);
    }

    /**
     * Store multiple items in the cache for a given number of minutes.
     *
     * @param  array $values
     * @param  int $ttl  - seconds for Laravel >= 5.8 or minutes for Laravel <= 5.7
     * @return void
     */
    public function putMany(array $values, $ttl = null) {
        $this->sendTagsToStore();
        return parent::putMany($values, $ttl);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function forever($key, $value) {
        $this->sendTagsToStore();
        return parent::forever($key, $value);
    }

    /**
     * @return AlternativeCacheStore
     */
    public function getStore() {
        return $this->store;
    }

    /**
     * @return AlternativeCacheStore
     */
    protected function sendTagsToStore() {
        return $this->getStore()->_setTagsForNextOperation($this->tags->getKeys());
    }

    /**
     * Needed to prevent laravel's tag key modification
     *
     * @param string $key
     * @return string
     */
    protected function itemKey($key) {
        return $key;
    }

    /**
     * Needed to prevent laravel's tag key modification
     *
     * @param string $key
     * @return string
     * @throws \BadMethodCallException
     */
    public function taggedItemKey($key) {
        throw new \BadMethodCallException('Method taggedItemKey() is not used in AlternativeTaggedCache');
    }

    /**
     * Set the cache key prefix.
     *
     * @param  string $prefix
     * @return void
     */
    public function setPrefix($prefix) {
        $this->prefix = $prefix;
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix() {
        return $this->prefix;
    }

}
