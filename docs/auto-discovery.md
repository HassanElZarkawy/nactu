# Auto-Discovery

Nactu can automatically discover handlers for your commands and queries based on naming conventions — no manual registration required.

## How It Works

When auto-discovery is enabled (default), the bus resolves handlers by convention:

### Commands

```
App\Commands\CreateUser\CreateUserCommand → App\Commands\CreateUser\CreateUserHandler
App\Commands\DeleteUser\DeleteUserCommand → App\Commands\DeleteUser\DeleteUserHandler
```

### Queries

```
App\Queries\ListUsers\ListUsersQuery → App\Queries\ListUsers\ListUsersHandler
App\Queries\GetUser\GetUserQuery     → App\Queries\GetUser\GetUserHandler
```

## Conventions

1. The handler is resolved in the same namespace as its message — handlers live next to the commands and queries they handle
2. The `Command` / `Query` suffix is replaced with `Handler` (in any sub-namespace under the source namespace)
3. The handler class must exist

## Configuration

```php
// config/nactu.php
'auto_discover' => [
    'enabled' => (bool) env('NACTU_AUTO_DISCOVER_ENABLED', true),

    // Commands
    'commands_namespace' => env('NACTU_AUTO_DISCOVER_COMMANDS_NAMESPACE', 'App\\Commands'),
    'handlers_namespace' => env('NACTU_AUTO_DISCOVER_HANDLERS_NAMESPACE', 'App\\Commands'),

    // Queries
    'queries_namespace' => env('NACTU_AUTO_DISCOVER_QUERIES_NAMESPACE', 'App\\Queries'),
    'query_handlers_namespace' => env('NACTU_AUTO_DISCOVER_QUERY_HANDLERS_NAMESPACE', 'App\\Queries'),
],
```

## Custom Namespaces

By default, handlers are co-located with their messages. If your project keeps handlers in separate directories, point the handler namespaces there:

```php
'auto_discover' => [
    'enabled' => true,
    'commands_namespace' => 'Domain\\Users\\Commands',
    'handlers_namespace' => 'Domain\\Users\\Handlers',
    'queries_namespace' => 'Domain\\Users\\Queries',
    'query_handlers_namespace' => 'Domain\\Users\\QueryHandlers',
],
```

Note that the generators always write handlers next to their messages; the handler namespace keys only affect auto-discovery.

## Disabling Auto-Discovery

Set `enabled` to `false` and register handlers manually:

```php
// config/nactu.php
'auto_discover' => [
    'enabled' => false,
],
```

```php
use Nactu\CommandBusInterface;
use Nactu\QueryBusInterface;

public function boot(CommandBusInterface $commands, QueryBusInterface $queries): void
{
    $commands->register(CreateUserCommand::class, CreateUserHandler::class);
    $queries->register(ListUsersQuery::class, ListUsersHandler::class);
}
```

## Mixed Registration

You can use auto-discovery alongside manual registration. Manually registered handlers take priority:

```php
// This overrides the auto-discovered handler
$bus->register(SpecialCommand::class, CustomSpecialHandler::class);
```
