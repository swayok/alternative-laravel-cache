<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Pool;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\TagInterop\TaggableCacheItemInterface;
use League\Flysystem\Filesystem;
use Psr\Cache\CacheItemInterface;

class HierarchialFilesystemCachePoolFlysystem3 extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    public const CACHE_PATH = 'cache';

    /**
     * @type Filesystem
     */
    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->filesystem->createDirectory(self::CACHE_PATH);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDirectValue($key)
    {
        [$isHit, $value] = $this->fetchObjectFromCache($key);
        return $isHit ? $value : null;
    }

    /**
     * @param string $key
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getFilePath(string $key): string
    {
        if (!preg_match('%^[a-zA-Z0-9_.! |]+$%', $key)) {
            throw new \InvalidArgumentException(sprintf('Invalid key "%s". Valid keys must match [a-zA-Z0-9_\.! ].', $key));
        }
        $key = str_replace(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, '/', $key);

        return sprintf('%s/%s', rtrim(self::CACHE_PATH, '/\\'), ltrim($key, '/\\'));
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        $this->saveTags($item);
        return parent::save($item);
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(PhpCacheItem $item, $ttl)
    {
        $file = $this->getFilePath($item->getKey());
        if ($this->filesystem->has($file)) {
            $this->filesystem->delete($file);
        }

        $tags = $item->getTags();

        $this->filesystem->write(
            $file,
            serialize([
                $ttl === null ? null : time() + $ttl,
                $item->get(),
                $tags,
            ])
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        $file = $this->getFilePath($key);
        if (!$this->filesystem->has($file)) {
            return [false, null, []];
        }

        /** @noinspection UnserializeExploitsInspection */
        $data = unserialize($this->filesystem->read($file));
        if ($data[0] !== null && time() > $data[0]) {
            foreach ($data[2] as $tag) {
                $this->removeListItem($this->getTagKey($tag), $key);
            }
            $this->forceClear($key);

            return [false, null, []];
        }

        return [true, $data[1], $data[2]];
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        $file = $this->getFilePath($name);

        if (!$this->filesystem->has($file)) {
            $this->filesystem->write($file, serialize([]));
        }

        return unserialize($this->filesystem->read($file));
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        $file = $this->getFilePath($name);
        $this->filesystem->delete($file);
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $key)
    {
        $list = $this->getList($name);
        $list[] = $key;

        $this->filesystem->write($this->getFilePath($name), serialize($list));
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeListItem($name, $key)
    {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        $this->filesystem->write($this->getFilePath($name), serialize($list));
        return true;
    }

    protected function forceClear(string $key): bool
    {
        $path = $this->getFilePath($key);
        if ($this->filesystem->directoryExists($path)) {
            $this->filesystem->deleteDirectory($path);
        } else {
            $this->filesystem->delete($path);
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $this->preRemoveItem($key);

        return $this->forceClear($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        $this->filesystem->deleteDirectory(self::CACHE_PATH);
        $this->filesystem->createDirectory(self::CACHE_PATH);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemoveItem($key)
    {
        try {
            $tags = $this->getItem($key)->getTags();
        } catch (CachePoolException $exc) {
            if (!$exc->getPrevious() || strpos('file_get_contents(', $exc->getPrevious()->getMessage()) !== false) {
                throw $exc;
            }
        }
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $this->removeListItem($this->getTagKey($tag), $key);
            }
        }

        return $this;
    }
}
