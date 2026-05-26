# Auto-Discovery

Nactu can automatically discover handlers for your commands and queries based on naming conventions — no manual registration required.

## How It Works

When auto-discovery is enabled (default), the bus resolves handlers by convention:

### Commands

```
App\Commands\CreateUserCommand → App\Handlers\CreateUserHandler
App\Commands\DeleteUserCommand → App\Handlers\DeleteUserHandler
```

### Queries

```
App\Queries\ListUsersQuery → App\QueryHandlers\ListUsersHandler
App\Queries\GetUserQuery   → App\QueryHandlers\GetUserHandler
```

## Conventions

1. The command/query suffix is replaced with `Handler`
2. The source namespace is replaced with the handler namespace
3. The handler class must exist

## Configuration

```php
// config/nactu.php
'auto_discover' => [
    'enabled' => (bool) env('NACTU_AUTO_DISCOVER_ENABLED', true),

    // Commands
    'commands_namespace' => env('NACTU_AUTO_DISCOVER_COMMANDS_NAMESPACE', 'App\\Commands'),
    'handlers_namespace' => env('NACTU_AUTO_DISCOVER_HANDLERS_NAMESPACE', 'App\\Handlers'),

    // Queries
    'queries_namespace' => env('NACTU_AUTO_DISCOVER_QUERIES_NAMESPACE', 'App\\Queries'),
    'query_handlers_namespace' => env('NACTU_AUTO_DISCOVER_QUERY_HANDLERS_NAMESPACE', 'App\\QueryHandlers'),
],
```

## Custom Namespaces

If your project uses a different structure, update the config:

```php
'auto_discover' => [
    'enabled' => true,
    'commands_namespace' => 'Domain\\Users\\Commands',
    'handlers_namespace' => 'Domain\\Users\\Handlers',
    'queries_namespace' => 'Domain\\Users\\Queries',
    'query_handlers_namespace' => 'Domain\\Users\\QueryHandlers',
],
```

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
