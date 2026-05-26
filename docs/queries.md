# Queries

Queries represent **read operations** — they fetch data without modifying state. They are dispatched through the `QueryBusInterface` and come with built-in caching support.

## Creating a Query

Use the artisan command to generate a query and its handler:

```bash
php artisan make:query ListUsers
```

This creates two files:

**`App\Queries\ListUsersQuery`**
```php
namespace App\Queries;

readonly class ListUsersQuery
{
    public function __construct(
        public string $status = 'active',
        public int $page = 1,
    ) {}
}
```

**`App\QueryHandlers\ListUsersHandler`**
```php
namespace App\QueryHandlers;

use App\Queries\ListUsersQuery;

class ListUsersHandler
{
    public function handle(ListUsersQuery $query): array
    {
        return User::where('status', $query->status)
            ->paginate(15, page: $query->page)
            ->toArray();
    }
}
```

## Dispatching Queries

Inject `QueryBusInterface` and call `dispatch`:

```php
use Nactu\QueryBusInterface;

class UserController extends Controller
{
    public function index(QueryBusInterface $bus): JsonResponse
    {
        $users = $bus->dispatch(new ListUsersQuery(
            status: 'active',
            page: 1,
        ));

        return response()->json($users);
    }
}
```

## Cacheable Queries

Add the `--cacheable` (or `-c`) flag to generate a query that implements `CacheableQuery`:

```bash
php artisan make:query ListUsers -c
```

This generates a query with caching methods:

```php
namespace App\Queries;

use Nactu\Contracts\CacheableQuery;

readonly class ListUsersQuery implements CacheableQuery
{
    public function __construct(
        public string $status = 'active',
    ) {}

    public function getCacheTtl(): ?int
    {
        return null; // uses default TTL from config (300s)
    }

    public function getCacheTags(): array
    {
        return ['users'];
    }

    public function shouldBypassCache(): bool
    {
        return false;
    }
}
```

### Custom TTL

Return a number in seconds to override the default TTL:

```php
public function getCacheTtl(): ?int
{
    return 600; // cache for 10 minutes
}
```

### Cache Tags

Tags allow you to invalidate groups of cached queries at once:

```php
public function getCacheTags(): array
{
    return ['users', 'users:active'];
}
```

> **Note:** Cache tags require a cache driver that supports tagging (e.g., Redis, Memcached, Database). The `file` and `array` drivers do not support tags.

### Bypassing Cache

Return `true` to skip the cache for a specific query instance:

```php
public function shouldBypassCache(): bool
{
    return $this->fresh;
}
```

```php
// Force fresh results
$users = $bus->dispatch(new ListUsersQuery(status: 'active', fresh: true));
```

## Manual Handler Registration

By default, handlers are [auto-discovered](auto-discovery.md). You can also register them manually:

```php
use Nactu\QueryBusInterface;

public function boot(QueryBusInterface $bus): void
{
    $bus->register(
        ListUsersQuery::class,
        ListUsersHandler::class,
    );
}
```

## Built-in Middleware

The query bus comes with the following middleware:

| Middleware               | Purpose                            |
|--------------------------|------------------------------------|
| **CacheQueryMiddleware** | Caches query results automatically |
| **PerformanceMonitor**   | Logs slow query executions         |

See [Middleware](middleware.md) and [Caching](caching.md) for details.
