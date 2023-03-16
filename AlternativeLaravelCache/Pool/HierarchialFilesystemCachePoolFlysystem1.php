<?php
/** @noinspection PhpUndefinedMethodInspection */

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace AlternativeLaravelCache\Pool;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\Exception\CachePoolException;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Hierarchy\HierarchicalCachePoolTrait;
use Cache\Hierarchy\HierarchicalPoolInterface;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\RootViolationException;
use League\Flysystem\FileExistsException;
use League\Flysystem\Filesystem;
use League\Flysystem\Util;
use Psr\Cache\CacheItemInterface;

class HierarchialFilesystemCachePoolFlysystem1 extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    public const CACHE_PATH = 'cache';

    /**
     * @type Filesystem
     */
    protected $filesystem;

    /**
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->filesystem->createDir(self::CACHE_PATH);
    }

    /**
     * Get a value form the store. This must not be an PoolItemInterface.
     *
     * @param string $key
     *
     * @return string|null
     * @throws \LogicException
     * @throws RootViolationException
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws \InvalidArgumentException
     */
    protected function getDirectValue($key): ?string
    {
        [$isHit, $value] = $this->fetchObjectFromCache($key);
        return $isHit ? $value : null;
    }

    /**
     * @param string $key
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getFilePath($key): string
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
     * @throws \InvalidArgumentException
     * @throws FileNotFoundException
     * @throws FileExistsException
     */
    protected function storeItemInCache(PhpCacheItem $item, $ttl)
    {
        $file = $this->getFilePath($item->getKey());
        if ($this->filesystem->has($file)) {
            $this->filesystem->delete($file);
        }

        $tags = $item->getTags();

        /** @noinspection PhpVoidFunctionResultUsedInspection */
        return $this->filesystem->write(
            $file,
            serialize([
                $ttl === null ? null : time() + $ttl,
                $item->get(),
                $tags,
            ])
        );
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     * @throws FileNotFoundException
     * @throws FileExistsException
     * @throws RootViolationException
     * @throws \LogicException
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
     * @throws FileNotFoundException
     * @throws \InvalidArgumentException
     * @throws FileExistsException
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
     * @throws FileNotFoundException
     * @throws \InvalidArgumentException
     */
    protected function removeList($name)
    {
        $file = $this->getFilePath($name);
        $this->filesystem->delete($file);
    }

    /**
     * {@inheritdoc}
     * @throws FileNotFoundException
     * @throws \InvalidArgumentException
     * @throws FileExistsException
     */
    protected function appendListItem($name, $key)
    {
        $list = $this->getList($name);
        $list[] = $key;

        return $this->filesystem->update($this->getFilePath($name), serialize($list));
    }

    /**
     * {@inheritdoc}
     * @throws FileNotFoundException
     * @throws \InvalidArgumentException
     * @throws FileExistsException
     */
    protected function removeListItem($name, $key)
    {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        return $this->filesystem->update($this->getFilePath($name), serialize($list));
    }

    /**
     * @throws \LogicException
     * @throws RootViolationException
     * @throws \InvalidArgumentException
     */
    protected function forceClear(string $key): bool
    {
        try {
            $path = Util::normalizePath($this->getFilePath($key));
            if ($this->filesystem->get($path)->isDir()) {
                return $this->filesystem->deleteDir($path);
            }

            // Flysystem v1 returns bool from delete
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            return $this->filesystem->delete($path);
        } catch (FileNotFoundException $e) {
            return true;
        }
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     * @throws RootViolationException
     * @throws \LogicException
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    protected function clearOneObjectFromCache($key)
    {
        $this->preRemoveItem($key);

        return $this->forceClear($key);
    }

    /**
     * {@inheritdoc}
     * @throws RootViolationException
     */
    protected function clearAllObjectsFromCache()
    {
        $this->filesystem->deleteDir(self::CACHE_PATH);
        $this->filesystem->createDir(self::CACHE_PATH);

        return true;
    }

    /**
     * Removes the key form all tag lists.
     *
     * @param string $key
     * @return $this
     * @throws FileNotFoundException
     * @throws FileExistsException
     * @throws \InvalidArgumentException
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
