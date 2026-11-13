# Nactu

A lightweight CQRS package for Laravel with command/query bus, middleware pipeline, query caching, and retry support.

## Requirements

- PHP 8.4+
- Laravel 13+

## Installation

```bash
composer require hassanelzarkawy/nactu
```

Publish the config file:

```bash
php artisan vendor:publish --tag=nactu-config
```

## Quick Start

### Commands (Write)

Create a command and its handler:

```bash
php artisan nactu:command CreateUser
```

This generates:
- `App\Commands\CreateUser\CreateUserCommand`
- `App\Commands\CreateUser\CreateUserHandler`

Dispatch it:

```php
use Nactu\CommandBusInterface;

public function store(CommandBusInterface $bus): void
{
    $bus->dispatch(new CreateUserCommand(
        name: 'Hassan',
        email: 'hassan@example.com',
    ));
}
```

### Queries (Read)

Create a query and its handler:

```bash
php artisan nactu:query ListUsers
```

This generates:
- `App\Queries\ListUsers\ListUsersQuery`
- `App\Queries\ListUsers\ListUsersHandler`

Dispatch it:

```php
use Nactu\QueryBusInterface;

public function index(QueryBusInterface $bus): JsonResponse
{
    $users = $bus->dispatch(new ListUsersQuery(
        status: 'active',
    ));

    return response()->json($users);
}
```

## Documentation

- [Commands](docs/commands.md) — Command bus, handlers, and write operations
- [Queries](docs/queries.md) — Query bus, cacheable queries, and read operations
- [Middleware](docs/middleware.md) — Built-in and custom middleware
- [Caching](docs/caching.md) — Query caching, cache keys, and invalidation
- [Auto-Discovery](docs/auto-discovery.md) — Convention-based handler resolution
- [Console Commands](docs/console-commands.md) — Code generation commands
- [Configuration](docs/configuration.md) — Full config reference

## How It Works

```
CommandBus (writes)          QueryBus (reads)
     │                            │
     ├─ Retry Middleware          ├─ Cache Middleware
     ├─ Performance Monitor       ├─ Performance Monitor
     │                            │
     └─► Handler                  └─► Handler
```

Commands and queries have separate middleware stacks. Commands get retry support, queries get caching — no overlap.

## License

MIT
