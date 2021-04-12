<?php

namespace AlternativeLaravelCache\Store;

use Illuminate\Cache\HasCacheLock;

class AlternativeHierarchialFileCacheStoreWithLocks extends AlternativeHierarchialFileCacheStore {

    use HasCacheLock;

}
