<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Pool;

use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Common\Exception\CachePoolException;
use AlternativeLaravelCache\Vendors\Common\PhpCacheItem;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalCachePoolTrait;
use AlternativeLaravelCache\Vendors\Hierarchy\HierarchicalPoolInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Psr\Cache\CacheItemInterface;

class HierarchicalFilesystemCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    protected Filesystem $filesystem;

    /**
     * Absolute path to cache folder. Should not end with a slash.
     */
    protected string $folder;

    protected ?int $filePermission;

    protected int $directoryPermission;

    public function __construct(
        Filesystem $filesystem,
        string $folder,
        ?int $filePermission = null,
        int $directoryPermission = 0755
    ) {
        $this->filesystem = $filesystem;
        $this->folder = rtrim($folder, '/\\');
        $this->filePermission = $filePermission;
        $this->directoryPermission = $directoryPermission;
        $this->ensureFolderExists();
    }

    /**
     * {@inheritdoc}
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    protected function getDirectValue($key)
    {
        [$isHit, $value] = $this->fetchObjectFromCache($key);
        return $isHit ? $value : null;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function getFilePath(string $key): string
    {
        if (!preg_match('%^[a-zA-Z0-9_.! |]+$%', $key)) {
            throw new \InvalidArgumentException(sprintf('Invalid key "%s". Valid keys must match [a-zA-Z0-9_\.! ].', $key));
        }
        $key = str_replace(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, '/', $key);
        foreach (explode('/', $key) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException(sprintf('Invalid key "%s". Path traversal is not allowed.', $key));
            }
        }

        return sprintf('%s/%s', $this->folder, ltrim($key, '/\\'));
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item): bool
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
        if ($this->filesystem->isFile($file)) {
            $this->filesystem->delete($file);
        }

        return $this->writeFile(
            $file,
            serialize([
                $ttl === null ? null : time() + $ttl,
                $item->get(),
                $item->getTags(),
            ])
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        $contents = $this->readFile($this->getFilePath($key));
        if ($contents === null) {
            return [false, null, []];
        }

        $data = $this->unserializeValue($contents);
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
        if (!$this->filesystem->isFile($file)) {
            $this->writeFile($file, serialize([]));
        }

        return $this->unserializeValue($this->readFile($file) ?? serialize([]));
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        $this->filesystem->delete($this->getFilePath($name));
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $key)
    {
        $list = $this->getList($name);
        $list[] = $key;

        return $this->writeFile($this->getFilePath($name), serialize($list));
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

        return $this->writeFile($this->getFilePath($name), serialize($list));
    }

    protected function forceClear(string $key): bool
    {
        $path = $this->getFilePath($key);
        if ($this->filesystem->isDirectory($path)) {
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
        $this->filesystem->deleteDirectory($this->folder);
        $this->ensureFolderExists();

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

    protected function ensureFolderExists(): void
    {
        $this->filesystem->ensureDirectoryExists($this->folder, $this->directoryPermission, true);
    }

    protected function readFile(string $path): ?string
    {
        if (!$this->filesystem->isFile($path)) {
            return null;
        }
        try {
            return $this->filesystem->get($path, true);
        } catch (FileNotFoundException $e) {
            return null;
        }
    }

    protected function writeFile(string $path, string $data): bool
    {
        $this->filesystem->ensureDirectoryExists(dirname($path), $this->directoryPermission, true);
        if ($this->filesystem->put($path, $data, true) === false) {
            return false;
        }
        if ($this->filePermission !== null) {
            $this->filesystem->chmod($path, $this->filePermission);
        }

        return true;
    }
}
