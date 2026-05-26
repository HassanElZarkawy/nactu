# Commands

Commands represent **write operations** — things that change state. They are dispatched through the `CommandBusInterface` and handled by dedicated handler classes.

## Creating a Command

Use the artisan command to generate a command and its handler:

```bash
php artisan make:command CreateUser
```

This creates two files:

**`App\Commands\CreateUserCommand`**
```php
namespace App\Commands;

readonly class CreateUserCommand
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
```

**`App\Handlers\CreateUserHandler`**
```php
namespace App\Handlers;

use App\Commands\CreateUserCommand;

class CreateUserHandler
{
    public function handle(CreateUserCommand $command): void
    {
        // Create the user...
    }
}
```

## Dispatching Commands

Inject `CommandBusInterface` and call `dispatch`:

```php
use Nactu\CommandBusInterface;

class UserController extends Controller
{
    public function store(Request $request, CommandBusInterface $bus): RedirectResponse
    {
        $bus->dispatch(new CreateUserCommand(
            name: $request->input('name'),
            email: $request->input('email'),
        ));

        return redirect()->route('users.index');
    }
}
```

## Manual Handler Registration

By default, handlers are [auto-discovered](auto-discovery.md). You can also register them manually:

```php
use Nactu\CommandBusInterface;

public function boot(CommandBusInterface $bus): void
{
    $bus->register(
        CreateUserCommand::class,
        CreateUserHandler::class,
    );
}
```

## Returning Values

Commands can return values from their handlers:

```php
class CreateUserHandler
{
    public function handle(CreateUserCommand $command): User
    {
        return User::create([
            'name' => $command->name,
            'email' => $command->email,
        ]);
    }
}
```

```php
$user = $bus->dispatch(new CreateUserCommand(...));
```

## Using __invoke

If your handler doesn't have a `handle` method, the bus will call `__invoke`:

```php
class DeleteUserHandler
{
    public function __invoke(DeleteUserCommand $command): void
    {
        User::findOrFail($command->id)->delete();
    }
}
```

## Built-in Middleware

The command bus comes with the following middleware:

| Middleware             | Purpose                                          |
|------------------------|--------------------------------------------------|
| **CommandRetrier**     | Retries failed commands with exponential backoff |
| **PerformanceMonitor** | Logs slow command executions                     |

See [Middleware](middleware.md) for details and customization.
