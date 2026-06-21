<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\HasCacheLock;

class AlternativeHierarchicalFileCacheStoreWithLocks extends AlternativeHierarchicalFileCacheStore
{
    use HasCacheLock;
}
