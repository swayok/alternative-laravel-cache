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
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FilesystemCachePool extends AbstractCachePool
{
    private Filesystem $filesystem;

    /**
     * Absolute path to cache folder. Should not end with a slash.
     */
    private string $folder;

    private ?int $filePermission;

    private int $directoryPermission;

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

    public function setFolder(string $folder): void
    {
        $this->folder = rtrim($folder, '/\\');
    }

    protected function fetchObjectFromCache($key): array
    {
        $empty = [false, null, [], null];
        $file = $this->getFilePath($key);

        $contents = $this->readFile($file);
        if ($contents === null) {
            return $empty;
        }

        $data = @$this->unserializeValue($contents);
        if ($data === false) {
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

    protected function clearAllObjectsFromCache(): bool
    {
        $this->filesystem->deleteDirectory($this->folder);
        $this->ensureFolderExists();

        return true;
    }

    protected function clearOneObjectFromCache($key): bool
    {
        return $this->forceClear($key);
    }

    protected function storeItemInCache(PhpCacheItem $item, $ttl): bool
    {
        $data = serialize(
            [
                $item->get(),
                $item->getTags(),
                $item->getExpirationTimestamp(),
            ]
        );

        return $this->writeFile($this->getFilePath($item->getKey()), $data);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getFilePath(string $key): string
    {
        if (! preg_match('|^[a-zA-Z0-9_.! ]+$|', $key) || $key === '.' || $key === '..') {
            throw new InvalidArgumentException(sprintf('Invalid key "%s". Valid filenames must match [a-zA-Z0-9_\.! ].', $key));
        }

        return sprintf('%s/%s', $this->folder, $key);
    }

    protected function getList($name)
    {
        $file = $this->getFilePath($name);
        if (! $this->filesystem->isFile($file)) {
            $this->writeFile($file, serialize([]));
        }

        return $this->unserializeValue($this->readFile($file) ?? serialize([]));
    }

    protected function removeList($name)
    {
        $this->filesystem->delete($this->getFilePath($name));
    }

    protected function appendListItem($name, $key): bool
    {
        $list = $this->getList($name);
        $list[] = $key;

        return $this->writeFile($this->getFilePath($name), serialize($list));
    }

    protected function removeListItem($name, $key): bool
    {
        $list = $this->getList($name);
        foreach ($list as $i => $item) {
            if ($item === $key) {
                unset($list[$i]);
            }
        }

        return $this->writeFile($this->getFilePath($name), serialize($list));
    }

    private function forceClear($key): bool
    {
        $path = $this->getFilePath($key);
        $this->filesystem->delete($path);

        return ! $this->filesystem->isFile($path);
    }

    private function ensureFolderExists(): void
    {
        $this->filesystem->ensureDirectoryExists($this->folder, $this->directoryPermission, true);
    }

    private function readFile(string $path): ?string
    {
        try {
            return $this->filesystem->get($path, true);
        } catch (FileNotFoundException $e) {
            return null;
        }
    }

    private function writeFile(string $path, string $data): bool
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
