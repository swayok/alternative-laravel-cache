<?php

namespace AlternativeLaravelCache\Pool;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\Taggable\TaggableItemInterface;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\Util;
use Psr\Cache\CacheItemInterface;

/**
 * Lets say "thanks" to people who make important class methods be private so you need to duplicate lots of code
 * to extend a class instead of just overriding single method... =(( Burn in hell!
 */
class HierarchialFilesystemCachePool extends FilesystemCachePool implements HierarchicalPoolInterface {

    use HierarchicalCachePoolTrait;

    /**
     * @type Filesystem
     */
    protected $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem) {
        $this->filesystem = $filesystem;
        parent::__construct($filesystem);
    }

    /**
     * Get a value form the store. This must not be an PoolItemInterface.
     *
     * @param string $key
     *
     * @return string|null
     * @throws \LogicException
     * @throws \League\Flysystem\RootViolationException
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \InvalidArgumentException
     */
    protected function getValueFormStore($key) {
        list($isHit, $value, $tags) = $this->fetchObjectFromCache($key);
        return $isHit ? $value : null;
    }

    /**
     * @param string $key
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function getFilePath($key) {
        if (!preg_match('%^[a-zA-Z0-9_\.! |]+$%', $key)) {
            throw new \InvalidArgumentException(sprintf('Invalid key "%s". Valid keys must match [a-zA-Z0-9_\.! ].', $key));
        }
        $key = str_replace(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, '/', $key);

        return sprintf('%s/%s', self::CACHE_PATH, $key);
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \League\Flysystem\FileExistsException
     */
    protected function storeItemInCache(CacheItemInterface $item, $ttl) {
        $file = $this->getFilePath($item->getKey());
        if ($this->filesystem->has($file)) {
            $this->filesystem->delete($file);
        }

        $tags = [];
        if ($item instanceof TaggableItemInterface) {
            $tags = $item->getTags();
        }

        return $this->filesystem->write($file, serialize([
            $ttl === null ? null : time() + $ttl,
            $item->get(),
            $tags,
        ]));
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\RootViolationException
     * @throws \LogicException
     */
    protected function fetchObjectFromCache($key) {
        $file = $this->getFilePath($key);
        if (!$this->filesystem->has($file)) {
            return [false, null, []];
        }

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
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileExistsException
     */
    protected function getList($name) {
        $file = $this->getFilePath($name);

        if (!$this->filesystem->has($file)) {
            $this->filesystem->write($file, serialize([]));
        }

        return unserialize($this->filesystem->read($file));
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \InvalidArgumentException
     */
    protected function removeList($name) {
        $file = $this->getFilePath($name);
        $this->filesystem->delete($file);
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileExistsException
     */
    protected function appendListItem($name, $key) {
        $list = $this->getList($name);
        $list[] = $key;

        return $this->filesystem->update($this->getFilePath($name), serialize($list));
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\FileExistsException
     */
    protected function removeListItem($name, $key) {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        return $this->filesystem->update($this->getFilePath($name), serialize($list));
    }

    /**
     * @param $key
     *
     * @return bool
     * @throws \LogicException
     * @throws \League\Flysystem\RootViolationException
     * @throws \InvalidArgumentException
     */
    protected function forceClear($key) {
        try {
            $path = Util::normalizePath($this->getFilePath($key));
            if ($this->filesystem->get($path)->isDir()) {
                return $this->filesystem->deleteDir($path);
            } else {
                return $this->filesystem->delete($path);
            }
        } catch (FileNotFoundException $e) {
            return true;
        }
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     * @throws \League\Flysystem\RootViolationException
     * @throws \LogicException
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    protected function clearOneObjectFromCache($key) {
        $this->preRemoveItem($key);

        return $this->forceClear($key);
    }

    /**
     * Removes the key form all tag lists.
     *
     * @param string $key
     *
     * @return $this
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \League\Flysystem\FileExistsException
     * @throws \InvalidArgumentException
     */
    protected function preRemoveItem($key) {
        $tags = $this->getItem($key)->getTags();
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $this->removeListItem($this->getTagKey($tag), $key);
            }
        }

        return $this;
    }
}