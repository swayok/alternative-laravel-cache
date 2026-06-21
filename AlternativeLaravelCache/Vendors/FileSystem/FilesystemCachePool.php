<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AlternativeLaravelCache\Vendors\FileSystem;

use AlternativeLaravelCache\Vendors\Common\AbstractCachePool;
use AlternativeLaravelCache\Vendors\Common\Exception\InvalidArgumentException;
use AlternativeLaravelCache\Vendors\Common\PhpCacheItem;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToDeleteFile;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FilesystemCachePool extends AbstractCachePool
{
    /**
     * @type Filesystem
     */
    private Filesystem $filesystem;

    /**
     * The folder should not begin nor end with a slash. Example: path/to/cache.
     *
     * @type string
     */
    private string $folder;

    /**
     * @param Filesystem $filesystem
     * @param string $folder
     *
     * @throws \League\Flysystem\FilesystemException
     */
    public function __construct(Filesystem $filesystem, string $folder = 'cache')
    {
        $this->folder = $folder;

        $this->filesystem = $filesystem;
        $this->filesystem->createDirectory($this->folder, []);
    }

    /**
     * @param string $folder
     */
    public function setFolder(string $folder)
    {
        $this->folder = $folder;
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FilesystemException
     */
    protected function fetchObjectFromCache($key): array
    {
        $empty = [false, null, [], null];
        $file = $this->getFilePath($key);

        try {
            /** @noinspection UnserializeExploitsInspection */
            $data = @unserialize($this->filesystem->read($file));
            if ($data === false) {
                return $empty;
            }
        } catch (FilesystemException $e) {
            return $empty;
        }

        // Determine expirationTimestamp from data, remove items if expired
        $expirationTimestamp = $data[2] ?: null;
        if ($expirationTimestamp !== null && time() > $expirationTimestamp) {
            foreach ($data[1] as $tag) {
                $this->removeListItem($this->getTagKey($tag), $key);
            }
            $this->forceClear($key);

            return $empty;
        }

        return [true, $data[0], $data[1], $expirationTimestamp];
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FilesystemException
     */
    protected function clearAllObjectsFromCache(): bool
    {
        $this->filesystem->deleteDirectory($this->folder);
        $this->filesystem->createDirectory($this->folder);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key): bool
    {
        return $this->forceClear($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(PhpCacheItem $item, $ttl): bool
    {
        $data = serialize(
            [
                $item->get(),
                $item->getTags(),
                $item->getExpirationTimestamp(),
            ]
        );

        $file = $this->getFilePath($item->getKey());
        try {
            $this->filesystem->write($file, $data);

            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * @param string $key
     *
     * @return string
     * @throws InvalidArgumentException
     *
     */
    private function getFilePath(string $key): string
    {
        if (! preg_match('|^[a-zA-Z0-9_.! ]+$|', $key)) {
            throw new InvalidArgumentException(sprintf('Invalid key "%s". Valid filenames must match [a-zA-Z0-9_\.! ].', $key));
        }

        return sprintf('%s/%s', $this->folder, $key);
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FilesystemException
     */
    protected function getList($name)
    {
        $file = $this->getFilePath($name);
        if (!$this->filesystem->has($file)) {
            $this->filesystem->write($file, serialize([]));
        }

        /** @noinspection UnserializeExploitsInspection */
        return unserialize($this->filesystem->read($file));
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FilesystemException
     */
    protected function removeList($name)
    {
        $file = $this->getFilePath($name);
        $this->filesystem->delete($file);
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FilesystemException
     */
    protected function appendListItem($name, $key): bool
    {
        $list = $this->getList($name);
        $list[] = $key;

        try {
            $this->filesystem->write($this->getFilePath($name), serialize($list));
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     * @throws \League\Flysystem\FilesystemException
     */
    protected function removeListItem($name, $key): bool
    {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        try {
            $this->filesystem->write($this->getFilePath($name), serialize($list));
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * @param $key
     *
     * @return bool
     */
    private function forceClear($key): bool
    {
        try {
            $this->filesystem->delete($this->getFilePath($key));

            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }
}
