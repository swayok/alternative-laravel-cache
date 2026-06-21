<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AlternativeLaravelCache\Vendors\Redis;

use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Common\PhpCacheItem;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalCachePoolTrait;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalPoolInterface;
use Predis\ClientInterface as Client;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PredisCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    /**
     * @type Client
     */
    protected $cache;

    /**
     * @param Client $cache
     */
    public function __construct(Client $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        /** @noinspection UnserializeExploitsInspection */
        if (false === $result = unserialize($this->cache->get($this->getHierarchyKey($key)))) {
            return [false, null, [], null];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        return 'OK' === $this->cache->flushdb()->getPayload();
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $path      = null;
        $keyString = $this->getHierarchyKey($key, $path);
        if ($path) {
            $this->cache->incr($path);
        }
        $this->clearHierarchyKeyCache();

        return $this->cache->del($keyString) >= 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(PhpCacheItem $item, $ttl)
    {
        if ($ttl < 0) {
            return false;
        }

        $key  = $this->getHierarchyKey($item->getKey());
        $data = serialize([true, $item->get(), $item->getTags(), $item->getExpirationTimestamp()]);

        if ($ttl === null || $ttl === 0) {
            return 'OK' === $this->cache->set($key, $data)->getPayload();
        }

        return 'OK' === $this->cache->setex($key, $ttl, $data)->getPayload();
    }

    /**
     * {@inheritdoc}
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    protected function getDirectValue($key)
    {
        return $this->cache->get($key);
    }

    /**
     * {@inheritdoc}
     * @param string $name
     * @param array $value
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    protected function appendListItem($name, $value)
    {
        $this->cache->lpush($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        return $this->cache->lrange($name, 0, -1);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        return $this->cache->del($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeListItem($name, $key)
    {
        return $this->cache->lrem($name, 0, $key);
    }
}
