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
     * Create a new tagged cache instance.
     *
     * @param  \Illuminate\Contracts\Cache\Store $store
     * @param  AlternativeTagSet $tags
     */
    public function __construct(Store $store, AlternativeTagSet $tags) {
        parent::__construct($store, $tags);
    }

    /**
     * Store an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @param  \DateTime|int $minutes
     * @return void
     */
    public function put($key, $value, $minutes = null) {
        $this->sendTagsToStore();
        parent::put($key, $value, $minutes);
    }

    /**
     * Store multiple items in the cache for a given number of minutes.
     *
     * @param  array $values
     * @param  int $minutes
     * @return void
     */
    public function putMany(array $values, $minutes) {
        $this->sendTagsToStore();
        parent::putMany($values, $minutes);
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
        parent::forever($key, $value);
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
}