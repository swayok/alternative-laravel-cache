<?php

declare(strict_types=1);

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\HasCacheLock;
use Illuminate\Contracts\Cache\LockProvider;

class AlternativeFileCacheStoreWithLocks extends AlternativeFileCacheStore implements LockProvider
{
    use HasCacheLock;
}
