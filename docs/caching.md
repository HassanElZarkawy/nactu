# Caching

Queries that implement the `CacheableQuery` interface are automatically cached by the `CacheQueryMiddleware`.

## How Cache Keys Are Generated

The `CacheKeyGenerator` creates deterministic, collision-resistant keys from query objects:

```
prefix:fully_qualified_class_name:property_hash
```

**Example:**

```php
$query = new ListUsersQuery(status: 'active', page: 2);

// Generated key (example):
// nactu:app_queries_list_users:3f2b9c1d8e4a7f601b2c3d4e5f6a7b8c9d
```

The key is generated from:
1. **Fully-qualified class name** — converted to snake_case, suffixes removed, namespace preserved (`App\Queries\ListUsersQuery` → `app_queries_list_users`). The namespace is included so two classes sharing a short name can never collide.
2. **All properties** — including private/protected, serialized and hashed with XXH128 (128-bit, so collisions are practically impossible)
3. **Prefix** — taken from the `cache.prefix` config by the middleware, or passed as second argument to `generateFromCommand`

### Deterministic Keys

The same query object always produces the same cache key, regardless of property declaration order:

```php
// These produce the same key
$query1 = new ListUsersQuery(status: 'active', page: 2);
$query2 = new ListUsersQuery(page: 2, status: 'active'); // same key
```

### Null Values Are Ignored

Properties with `null` values are excluded from the key, keeping keys shorter:

```php
$query = new SearchQuery(term: 'hello', filter: null);
// Key only includes: term=hello
```

## CacheableQuery Interface

Implement this interface on any query to enable caching:

```php
use Nactu\Contracts\CacheableQuery;

readonly class ListUsersQuery implements CacheableQuery
{
    public function __construct(
        public string $status = 'active',
    ) {}

    public function getCacheTtl(): ?int
    {
        return null;
    }

    public function getCacheTags(): array
    {
        return [];
    }

    public function shouldBypassCache(): bool
    {
        return false;
    }
}
```

### TTL (Time To Live)

- Return `null` to use the default TTL from config (300 seconds)
- Return an integer to set a custom TTL in seconds

```php
public function getCacheTtl(): ?int
{
    return 600; // 10 minutes
}
```

### Cache Tags

Group cached queries by tags for targeted invalidation:

```php
public function getCacheTags(): array
{
    return ['users', 'users:' . $this->status];
}
```

> **Note:** Tags require a cache driver that supports tagging — Redis, Memcached, database, and `array` do; `file`, `dynamodb`, and `octane` do not. Calling `->tags()` on a non-taggable store throws an exception.

### Bypassing Cache

Skip the cache for a specific query instance:

```php
public function shouldBypassCache(): bool
{
    return $this->noCache;
}
```

```php
// Always get fresh results
$users = $bus->dispatch(new ListUsersQuery(noCache: true));
```

## Cache Invalidation

The `CacheInvalidationService` provides methods to invalidate cached query results. It resolves its cache store from the `cache.store` config (the default store when `null`) and its default TTL from `cache.default_ttl`.

### Invalidate by Tags

```php
use Nactu\Services\CacheInvalidationService;

class CreateUserService
{
    public function __construct(
        private CacheInvalidationService $cacheInvalidation,
    ) {}

    public function create(CreateUserCommand $command): User
    {
        $user = User::create([...]);

        $this->cacheInvalidation->invalidateByTags(['users']);

        return $user;
    }
}
```

### Invalidate Specific Keys

```php
$this->cacheInvalidation->invalidateKeys('nactu:list_users:8a3f1b2c');
```

### Clear All Cache

```php
$this->cacheInvalidation->clearAll();
```

### Cache Warming

Pre-populate the cache:

```php
$this->cacheInvalidation->warm(
    key: 'nactu:list_users:8a3f1b2c',
    callback: fn () => User::where('status', 'active')->get(),
    ttl: 600,
    tags: ['users'],
);
```

## Manual Cache Key Generation

You can generate cache keys manually using the `CacheKeyGenerator`:

```php
use Nactu\Services\CacheKeyGenerator;

$generator = app(CacheKeyGenerator::class);

// From an object
$key = $generator->generateFromCommand($query, 'my_prefix');

// For a specific entity
$key = $generator->forEntity('user', 42);
// → "user:42"

$key = $generator->forEntity('user', 42, 'profile');
// → "user:42:profile"

// For a list query
$key = $generator->forList('users', ['status' => 'active'], page: 2);
// → "users:list:<hash>:page_2"
```
