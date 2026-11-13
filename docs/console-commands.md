# Console Commands

Nactu provides two Artisan commands to generate commands, queries, and their handlers.

Every use case lives in its own folder: the **last name segment is the use case**, any preceding segments are grouping namespaces. The message class and its handler are generated side by side inside that folder, sharing one namespace.

## nactu:command

Generate a command and its handler:

```bash
php artisan nactu:command CreateUser
```

**Options:**

| Option          | Flag | Description                                          |
|-----------------|------|------------------------------------------------------|
| `--force`       | `-f` | Overwrite existing files                             |
| `--cacheable`   | `-c` | Generate a cacheable command                         |
| `--handler-only`|      | Generate only the handler, not the command class     |

### Generated Files

**`app/Commands/CreateUser/CreateUserCommand.php`**
```php
namespace App\Commands\CreateUser;

readonly class CreateUserCommand
{
    public function __construct()
    {
    }
}
```

**`app/Commands/CreateUser/CreateUserHandler.php`**
```php
namespace App\Commands\CreateUser;

class CreateUserHandler
{
    public function handle(CreateUserCommand $command): mixed
    {
        //
    }
}
```

The handler shares the command's namespace, so no `use` import is needed.

### Domain Grouping

Segments before the last one group related use cases:

```bash
php artisan nactu:command Admin/CreateUser
# → app/Commands/Admin/CreateUser/CreateUserCommand.php
# → app/Commands/Admin/CreateUser/CreateUserHandler.php
```

```bash
php artisan nactu:command Billing/Invoice/SendInvoice
# → app/Commands/Billing/Invoice/SendInvoice/SendInvoiceCommand.php
# → app/Commands/Billing/Invoice/SendInvoice/SendInvoiceHandler.php
```

### Cacheable Command

The `--cacheable` flag generates a command that implements `CacheableQuery`:

```bash
php artisan nactu:command FindUser -c
```

This generates a command with caching methods (`getCacheTtl`, `getCacheTags`, `shouldBypassCache`).

### Handler Only

Generate just the handler for an existing command:

```bash
php artisan nactu:command CreateUser --handler-only
```

This creates `App\Commands\CreateUser\CreateUserHandler` without touching the command class.

## nactu:query

Generate a query and its handler:

```bash
php artisan nactu:query ListUsers
```

**Options:**

| Option          | Flag | Description                                          |
|-----------------|------|------------------------------------------------------|
| `--force`       | `-f` | Overwrite existing files                             |
| `--cacheable`   | `-c` | Generate a cacheable query                           |
| `--handler-only`|      | Generate only the handler, not the query class       |

### Generated Files

**`app/Queries/ListUsers/ListUsersQuery.php`**
```php
namespace App\Queries\ListUsers;

readonly class ListUsersQuery
{
    public function __construct()
    {
    }
}
```

**`app/Queries/ListUsers/ListUsersHandler.php`**
```php
namespace App\Queries\ListUsers;

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
php artisan nactu:query ListUsers -c
```

**`app/Queries/ListUsers/ListUsersQuery.php`**
```php
namespace App\Queries\ListUsers;

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

The commands automatically enforce naming conventions. The last input segment becomes the use-case folder; the `Command` / `Query` suffix is appended to the class inside it:

| Input                              | Use-case folder    | Generated classes                                          |
|------------------------------------|--------------------|------------------------------------------------------------|
| `nactu:command CreateUser`         | `Commands/CreateUser/` | `CreateUserCommand` + `CreateUserHandler`              |
| `nactu:command CreateUserCommand`  | `Commands/CreateUser/` | `CreateUserCommand` + `CreateUserHandler`              |
| `nactu:command Admin/CreateUser`   | `Commands/Admin/CreateUser/` | `CreateUserCommand` + `CreateUserHandler`        |
| `nactu:query ListUsers`            | `Queries/ListUsers/` | `ListUsersQuery` + `ListUsersHandler`                   |
| `nactu:query User/GetUserByCriteria` | `Queries/User/GetUserByCriteria/` | `GetUserByCriteriaQuery` + `GetUserByCriteriaHandler` |

The handler name is always derived from the message class by replacing the suffix (`XCommand` → `XHandler`, `XQuery` → `XHandler`). Providing the suffix in the input is optional — `CreateUser` and `CreateUserCommand` produce identical output.

## Already-Qualified Names

If the name starts with the root namespace (`App\...`), it is used as-is — only the missing suffix is appended. The use-case folder expansion is skipped:

```bash
php artisan nactu:command App\Commands\Legacy\ExportCommand
# → app/Commands/Legacy/ExportCommand.php
# → app/Commands/Legacy/ExportHandler.php
```
