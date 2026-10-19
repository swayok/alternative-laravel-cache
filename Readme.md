# What is this?
This is full-featured replacement for Laravel's Redis and file cache storages. All storages support proper tagging. 
Cache pools provided by http://www.php-cache.com/ + I've added `HierarchicalFilesystemCachePool` based on code of 
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

I was quite confused when attempted to use Laravel's version of tagging. Laravel's version works like folders (hierarchical cache), but not like tags. 
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

### For Laravel 10+

Add to `composer.json`:

    "require": {
        "swayok/alternative-laravel-cache": "6.1.*"
    }
    
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
    
    
### Declare ServiceProvider

### For Laravel 5.6+

Package auto-discovery will work.

### For Laravel < 5.6

Add to `config/app.php`: 

    $providers = [
        \AlternativeLaravelCache\Provider\AlternativeCacheStoresServiceProvider::class,
    ]
    
### Supported cache drivers

- `array` - array cache with proper tagging, also supports hierarchical cache keys and locks;  
- `redis` - redis cache with proper tagging, also supports hierarchical cache keys and locks;  
- `memcached` - memcached cache with proper tagging, also supports hierarchical cache keys and locks;
- `file` - simple file-based cache with proper tagging and locks;
- `hierarchical_file` - hierarchical file-based cache with proper tagging and locks.

`hierarchical_file` and `array` drivers also support `/` instead of `|` so you can use `/users/:uid/followers/:fid/likes` 
instead of `|users|:uid|followers|:fid|likes` as it better represents path in file system.

## Pipe character `|` in cache key (hierarchical cache keys)
Pipe character `|` for `array`, `redis`, `memcached` and `hierarchical_file` drivers works as hierarchy separator. This means that 
cache keys that contain `|` will work as hierarchy. 
Details here: http://www.php-cache.com/en/latest/hierarchy/

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
Slash character `/` for `hierarchical_file` and `array` driver works as hierarchy separator like pipe character `|`.
This was added to mimic folder structure.

Under the hood `/` is automatically converted to `|`, so `/users/12` key is equal to `|users|12`.

### `permissions` configuration parameter for file-based cache drivers (`config/cache.php`)

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'permissions' => [
                'file' => 0644,
                'dir' => 0755,
            ]
        ],
    ],

`file` is applied to cache files with `chmod` after they are written, `dir` is used when cache directories are created.
Both are merged with defaults (`0644` for files, `0755` for directories). Use plain octal integers as shown above.
Laravel's native `permission` option (single integer for files) is also supported and takes precedence over `permissions.file`.

## Restricting classes allowed during unserialization (`serializable_classes` and `serializable_base_classes`)

Cached values are stored serialized, and unserializing untrusted data can instantiate arbitrary classes (PHP object injection).
Laravel 12+ provides `cache.serializable_classes` to limit which classes may be restored from the cache.
This lib supports it and adds `cache.serializable_base_classes` to allow whole class hierarchies.

Supported by drivers: `redis`, `memcached`, `file`, `hierarchical_file` (including their variants with locks).
The `array` driver does not apply these settings.

### `serializable_classes`

    // config/cache.php
    'serializable_classes' => [
        App\Data\Money::class,
        App\Data\Address::class,
    ],

| Value | Behavior                                                                                   |
|-------|--------------------------------------------------------------------------------------------|
| `null` or not set | No restrictions (same as before this setting existed).                         |
| `true` | All classes are allowed.                                                                  |
| `false` | No classes are allowed. Scalars and arrays still work.                                   |
| `array` | Only listed classes are allowed (exact class names).                                     |

A class that is not allowed is not instantiated. PHP returns an `__PHP_Incomplete_Class` object in its place instead.
This is PHP's `allowed_classes` option of `unserialize()`, which matches **exact class names only**.
Listing `Illuminate\Database\Eloquent\Model` there will **not** allow `App\Models\User`.

### `serializable_base_classes`

To allow a class and everything that extends or implements it, list it here:

    // config/cache.php
    'serializable_classes' => false,
    'serializable_base_classes' => [
        Illuminate\Database\Eloquent\Model::class,
        Illuminate\Support\Collection::class,
        Stringable::class,
    ],

Now `App\Models\User` (extends `Model`) and any other model can be restored from the cache, while all other classes stay blocked.
Entries can be classes or interfaces.

How it works:

1. Before unserializing, the cache pool scans the stored string for serialized class names (`O:`, `C:` and enum `E:` entries).
2. Each found class name is checked with `is_a($class, $baseClass, true)` against every class listed in `serializable_base_classes`.
3. Matching classes are added to the allow-list built from `serializable_classes`, and the result is passed to `unserialize()` as `allowed_classes`.
4. Results are remembered per class name for the lifetime of the cache pool, so each class is checked once.

Things to know:

- `serializable_base_classes` only has an effect when `serializable_classes` is `false` or an array.
  With `null` or `true` nothing is restricted, so the base list is ignored.
- Only class names that match a base class are added to the allow-list, so a cached string that merely looks like a
  serialized object cannot allow anything outside of your base classes.
- Checking a class name with `is_a()` makes PHP's autoloader try to load it. Names that are not valid class names are
  skipped before that, but keep this in mind if untrusted data can reach your cache.
- Broad base classes allow broad hierarchies. Eloquent models have magic methods, so prefer a small, specific list.
- Existing cache entries do not need to be rewritten: the setting affects only how values are read.

## Notes
By default, service provider will replace Laravel's `array`, `redis` and `file` cache stores. 
You can alter this behavior like this:

    class MyAlternativeCacheStoresServiceProvider extends AlternativeCacheStoresServiceProvider {
        static protected $arrayDriverName = 'altarray';
        static protected $redisDriverName = 'altredis';
        static protected $memcacheDriverName = 'altmemcached';
        static protected $fileDriverName = 'altfile';
    }
    
File cache drivers use Laravel's `Illuminate\Filesystem\Filesystem` (the `files` binding) and work with local paths only,
like Laravel's native file cache.

