<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\HasCacheLock;

class AlternativeHierarchialFileCacheStoreWithLocks extends AlternativeHierarchialFileCacheStore
{
    use HasCacheLock;
}
