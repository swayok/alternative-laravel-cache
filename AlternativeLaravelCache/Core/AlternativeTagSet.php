<?php

namespace AlternativeLaravelCache\Core;

use Illuminate\Cache\TagSet;

class AlternativeTagSet extends TagSet {

    /**
     * The cache store implementation.
     *
     * @var AlternativeCacheStore
     */
    protected $store;

    public function reset() {
        $this->store->getWrappedConnection()->invalidateTags(array_map([$this, 'tagKey'], $this->names));
    }

    public function getNamespace() {
        throw new \BadMethodCallException('Method getNamespace() is not used in AlternativeTaggedCache');
    }

    public function tagId($name) {
        throw new \BadMethodCallException('Method tagId() is not used in AlternativeTaggedCache');
    }

    public function resetTag($name) {
        $key = $this->tagKey($name);
        $this->store->getWrappedConnection()->invalidateTag($key);
        return $key;
    }

    public function tagKey($name) {
        return $this->store->itemKey($name);
    }

    public function getKeys() {
        return array_map([$this, 'tagKey'], $this->names);
    }

}
