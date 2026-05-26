# Console Commands

Nactu provides Artisan commands to generate commands, queries, and their handlers.

## make:command

Generate a command and its handler:

```bash
php artisan make:command CreateUser
```

**Options:**

| Option        | Flag | Description                        |
|---------------|------|------------------------------------|
| `--force`     | `-f` | Overwrite existing files           |
| `--cacheable` | `-c` | Generate a cacheable query command |

### Generated Files

**`app/Commands/CreateUserCommand.php`**
```php
namespace App\Commands;

readonly class CreateUserCommand
{
    public function __construct()
    {
    }
}
```

**`app/Handlers/CreateUserHandler.php`**
```php
namespace App\Handlers;

use App\Commands\CreateUserCommand;

class CreateUserHandler
{
    public function handle(CreateUserCommand $command): mixed
    {
        //
    }
}
```

### Cacheable Command

The `--cacheable` flag generates a command that implements `CacheableQuery`. Useful when a command is also used as a query:

```bash
php artisan make:command FindUser -c
```

This generates a command with caching methods (`getCacheTtl`, `getCacheTags`, `shouldBypassCache`).

## make:query

Generate a query and its handler:

```bash
php artisan make:query ListUsers
```

**Options:**

| Option        | Flag | Description                |
|---------------|------|----------------------------|
| `--force`     | `-f` | Overwrite existing files   |
| `--cacheable` | `-c` | Generate a cacheable query |

### Generated Files

**`app/Queries/ListUsersQuery.php`**
```php
namespace App\Queries;

readonly class ListUsersQuery
{
    public function __construct()
    {
    }
}
```

**`app/QueryHandlers/ListUsersHandler.php`**
```php
namespace App\QueryHandlers;

use App\Queries\ListUsersQuery;

class ListUsersHandler
{
    public function handle(ListUsersQuery $query): mixed
    {
        //
    }
}
```

### Cacheable Query

The `--cacheable` flag generates a query that implements `CacheableQuery`:

```bash
php artisan make:query ListUsers -c
```

**`app/Queries/ListUsersQuery.php`**
```php
namespace App\Queries;

use Nactu\Contracts\CacheableQuery;

readonly class ListUsersQuery implements CacheableQuery
{
    public function __construct()
    {
    }

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

## Naming Conventions

The commands automatically enforce naming conventions:

| Input                            | Class Name          | File                             |
|----------------------------------|---------------------|----------------------------------|
| `make:command CreateUser`        | `CreateUserCommand` | `Commands/CreateUserCommand.php` |
| `make:command CreateUserCommand` | `CreateUserCommand` | `Commands/CreateUserCommand.php` |
| `make:query ListUsers`           | `ListUsersQuery`    | `Queries/ListUsersQuery.php`     |
| `make:query ListUsersQuery`      | `ListUsersQuery`    | `Queries/ListUsersQuery.php`     |

The `Command` / `Query` suffix is added automatically if not provided.
