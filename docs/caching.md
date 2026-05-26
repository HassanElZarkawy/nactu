# Caching

Queries that implement the `CacheableQuery` interface are automatically cached by the `CacheQueryMiddleware`.

## How Cache Keys Are Generated

The `CacheKeyGenerator` creates deterministic, collision-resistant keys from query objects:

```
prefix:query_name:property_hash
```

**Example:**

```php
$query = new ListUsersQuery(status: 'active', page: 2);

// Generated key (example):
// nactu:list_users:8a3f1b2c
```

The key is generated from:
1. **Class name** — converted to snake_case, suffixes removed (`ListUsersQuery` → `list_users`)
2. **All properties** — including private/protected, serialized and hashed via CRC32
3. **Prefix** — optional, passed as second argument to `generateFromCommand`

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

> **Note:** Tags require a cache driver that supports tagging (Redis, Memcached, Database). The `file` and `array` drivers do not support tags.

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

The `CacheInvalidationService` provides methods to invalidate cached query results.

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
// → "users:list:<hash>:page:2"
```
