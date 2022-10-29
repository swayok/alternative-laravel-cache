# What is this?
This is full-featured replacement for Laravel's Redis and file cache storages. All storages support proper tagging. 
Cache pools provided by http://www.php-cache.com/ + I've added `HierarchialFilesystemCachePool` based on code of 
`FilesystemCachePool` provided by http://www.php-cache.com/. All classes in this lib are proxies between Laravel's 
cache system and cache pools from http://www.php-cache.com/. I do not have any relation to php-cache.com and any cache pools there. 
And in result I cannot fix or change anything to the way cache pools are working. 

## What is proper tagging?
For example, you have:
    
    Cache::tags(['tag1', 'tag2'])->put('tag-test1', 'ok', 20);
    
How Laravel's native cache works with tags and Redis (Laravel 5.2):

    Cache::tags(['tag1', 'tag2'])->get('tag-test1');    //< 'ok'
    Cache::tags(['tag2', 'tag1'])->get('tag-test1');    //< null
    Cache::tags(['tag1'])->get('tag-test1');            //< null
    Cache::tags(['tag2'])->get('tag-test1');            //< null
    Cache::get('tag-test1');                            //< null
    Cache::forget('tag-test1');                         //< won't delete anything
    Cache::tags(['tag1', 'tag2'])->forget('tag-test1'); //< deleted
    Cache::tags(['tag2', 'tag1'])->forget('tag-test1'); //< won't delete anything
    Cache::tags(['tag1'])->forget('tag-test1');         //< won't delete anything
    Cache::tags(['tag2'])->forget('tag-test1');         //< won't delete anything
    Cache::tags(['tag1'])->flush();                     //< won't delete anything
    Cache::tags(['tag2'])->flush();                     //< won't delete anything
    Cache::tags(['tag1', 'tag2'])->flush();             //< flushed
    Cache::tags(['tag2', 'tag1'])->flush();             //< won't delete anything

If you think that this is correct behavior - go away, you don't need this lib.

I was quite confused when attempted to use Laravel's version of tagging. Laravel's version works like folders (hierarchial cache), but not like tags. 
I tried to understand for what purpose Laravel's tagging can be used and haven't found any. It's totally useless in almost any situation. 
Hopefully there are compatible drivers provided by http://www.php-cache.com/.

How it works with this lib:

    Cache::tags(['tag1', 'tag2'])->get('tag-test1');    //< 'ok' - use Cache::get('tag-test1') instead
    Cache::tags(['tag2', 'tag1'])->get('tag-test1');    //< 'ok' - use Cache::get('tag-test1') instead
    Cache::tags(['tag1'])->get('tag-test1');            //< 'ok' - use Cache::get('tag-test1') instead
    Cache::tags(['tag2'])->get('tag-test1');            //< 'ok' - use Cache::get('tag-test1') instead
    Cache::get('tag-test1');                            //< 'ok'
    Cache::forget('tag-test1');                         //< deleted
    Cache::tags(['tag1', 'tag2'])->forget('tag-test1'); //< deleted - use Cache::forget('tag-test1') instead
    Cache::tags(['tag2', 'tag1'])->forget('tag-test1'); //< deleted - use Cache::forget('tag-test1') instead
    Cache::tags(['tag1'])->forget('tag-test1');         //< deleted - use Cache::forget('tag-test1') instead
    Cache::tags(['tag2'])->forget('tag-test1');         //< deleted - use Cache::forget('tag-test1') instead
    Cache::tags(['tag1'])->flush();                     //< deleted all cache entries with tag 'tag1'
    Cache::tags(['tag2'])->flush();                     //< deleted all cache entries with tag 'tag2'
    Cache::tags(['tag1', 'tag2'])->flush();             //< deleted all cache entries with tag 'tag1' or 'tag2'
    Cache::tags(['tag2', 'tag1'])->flush();             //< deleted all cache entries with tag 'tag2' or 'tag1'

Note that tags here is like soft grouping for cache entries. 
This means that you do not need to specify tags to access/set/delete certain cache key. 
Cache key is the only thing you need to know to do this. 
Tags purpose is to give you a possibility to delete lots of cache entries with one line of code.
Tags are very useful when you need to store lots of entries related to same group and delete all cache entries at once when something changes.

For example:
1. You cache database records from `users` table in many places around you project tagging them with `users` tag.
2. You cache database records from `orders` table tagging them with both `users` and `orders` tags.
3. Some user updates his data and this action invalidates all cache entries related to this user and `users` table.
4. You need to remove all cache entries related to `users` (1 and 2) and you can do this just like this: `Cache::tags(['users'])->flush();`.

This way all cache entries created in 1 and 2 will be removed. And you won't need to know tags to access any cache entry by its key.

## How to use it:

### For Laravel 5.4+

Add to `composer.json`:

    "require": {
        "swayok/alternative-laravel-cache": "5.4.*"
    }
    
### For Laravel 5.3

Add to `composer.json`:

    "require": {
        "swayok/alternative-laravel-cache": "5.3.*"
    }
    
### Filesystem support

Add to `composer.json`:

    "require": {
        "swayok/cache-filesystem-adapter": "^1.0.0"
    }
    
### Redis support

To use `predis` add to `composer.json`:

    "require": {
        "cache/predis-adapter": "^1.0"
    }
    
To use `php-redis` extension add to `composer.json:

    "require": {
        "ext-redis": "*",
        "cache/redis-adapter": "^1.0"
    }

### Memcached support (NOT supported in Windows!)

Add to `composer.json`:

    "require": {
        "ext-memcached": "*",
        "cache/memcached-adapter": "^1.0"
    }

For Windows there are only `memcache` extension (without D at the end) but there are no such driver in Laravel.

### Declare ServiceProvider

### For Laravel 5.6+

Package auto-discovery will work.

### For Laravel < 5.6

Add to `config/app.php`: 

    $providers = [
        \AlternativeLaravelCache\Provider\AlternativeCacheStoresServiceProvider::class,
    ]
    
### Supported cache drivers

- `redis` - redis cache with proper tagging, also supports hierarchical cache keys;  
- `memcached` - memcached cache with proper tagging, also supports hierarchical cache keys;
- `file` - simple file-based cache with proper tagging;
- `hierarchial_file` - hierarchical file-based cache with proper tagging.
This driver also supports `/` instead of `|` so you can use `/users/:uid/followers/:fid/likes` instead of `|users|:uid|followers|:fid|likes`
as it better represents path in file system.

## Pipe character `|` in cache key (hierarchical cache keys)
Pipe character `|` for `redis`, `memcached` and `hierarchial_file` drivers works as hierarchy separator. This means that 
cache keys that contain `|` will work as hierarchy. Detals here: http://www.php-cache.com/en/latest/hierarchy/

    // Put key with colons (treated as usual cache key)
    Cache::put('cache-key:something:something-else', 'value', now()->addHours(6));
    
    // Put key with pipes (treated as hierarchical cache key)
    Cache::put('cache-key|something|something-else', 'value', now()->addHours(6));
    
    // Get key with colons
    Cache::get('cache-key:something:something-else');
    "value"
    
    // Get key with pipes
    Cache::get('cache-key|something|something-else');
    "value"
    
    // Forget call (it will both remove the cache key called 'cache-key' and whole hierarchy)
    Cache::forget('cache-key');
    
    // Get key with colons
    Cache::get('cache-key:something:something-else');
    "value"
    
    // Get key with pipes
    Cache::get('cache-key|something|something-else');
    null

## Slash character `/` in cache key
Slash character `/` for `hierarchial_file` driver works as hierarchy separator like pipe character `|`.
This was added to mimic folder structure.

### `permissions` configuration parameter for file-based cache drivers (`config/cache.php`)

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'permissions' => [
                'file' => [
                    'public' => 0644,
                ],
                'dir' => [
                    'public' => 0755,
                ],
            ]
        ],
    ],

These permissions passed to `vendor/league/flysystem/src/Adapter/Local.php`
and merged with default permissions. There are 2 types: `public` and `private`
but only `public` permissions will be used in `AlternativeLaravelCache`.

## Notes
By default, service provider will replace Laravel's `redis` and `file` cache stores. 
You can alter this behavior like this:

    class MyAlternativeCacheStoresServiceProvider extends AlternativeCacheStoresServiceProvider {
        static protected $redisDriverName = 'altredis';
        static protected $memcacheDriverName = 'altmemcached';
        static protected $fileDriverName = 'altfile';
    }
    
File cache storage currently supports only `'driver' => 'file'`. You can extend list of file cache drivers by  
overwriting `AlternativeCacheStoresServiceProvider->makeFileCacheAdapter()`.

Yep, there are not many tests right now and possibly there will never be more. 
