# Middleware

Middleware wraps the dispatch pipeline, allowing you to intercept commands and queries before or after they reach their handler.

## How It Works

Middleware follows the **onion pattern** — each middleware wraps the next one:

```
Middleware A (before)
  → Middleware B (before)
    → Handler
  ← Middleware B (after)
← Middleware A (after)
```

## Built-in Middleware

### Command Bus

| Middleware             | Execution Order | Purpose                                          |
|------------------------|-----------------|--------------------------------------------------|
| **CommandRetrier**     | 1st (outermost) | Retries failed commands with exponential backoff |
| **PerformanceMonitor** | 2nd             | Logs commands that exceed the slow threshold     |

### Query Bus

| Middleware               | Execution Order | Purpose                                     |
|--------------------------|-----------------|---------------------------------------------|
| **CacheQueryMiddleware** | 1st (outermost) | Caches query results                        |
| **PerformanceMonitor**   | 2nd             | Logs queries that exceed the slow threshold |

## CommandRetrier

Automatically retries commands that throw exceptions.

**Features:**
- Exponential backoff (only in console)
- Configurable non-retryable exceptions
- Configurable max attempts and backoff

```php
// Configured in config/nactu.php
'retry' => [
    'enabled' => true,
    'max_attempts' => 3,   // total attempts: the original attempt plus retries
    'max_backoff' => 3,    // max seconds to sleep between retries
    'except' => [          // exceptions that are never retried
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
    ],
],
```

> **Note:** `max_attempts` is the **total** number of attempts. The default of `3` means one original attempt plus two retries.

**Retry behavior:**

```php
// This handler will be retried up to 3 times on failure
class ProcessPaymentHandler
{
    public function handle(ProcessPaymentCommand $command): Receipt
    {
        $response = Http::post('/payments', [...]); // may fail

        return new Receipt($response->json());
    }
}
```

**Non-retryable exceptions:**

Exceptions listed in `retry.except` are never retried:

```php
// ValidationException — never retried
throw ValidationException::withMessages(['email' => 'Invalid email']);

// ModelNotFoundException — never retried
throw (new ModelNotFoundException)->setModel(User::class);
```

Add your own exceptions to the list in `config/nactu.php`:

```php
'retry' => [
    // ...
    'except' => [
        \Illuminate\Validation\ValidationException::class,
        \App\Exceptions\IrrecoverableException::class,
    ],
],
```

## CacheQueryMiddleware

Caches the result of queries that implement `CacheableQuery`. See [Caching](caching.md) for full details.

## PerformanceMonitor

Logs a warning when a command or query takes longer than the configured threshold:

```php
// config/nactu.php
'performance' => [
    'enabled' => true,
    'slow_threshold' => 1.0,  // seconds
],
```

When a slow execution is detected, you'll see a log entry like:

```
[WARNING] Slow command execution {"command":"App\\Commands\\ProcessPaymentCommand","duration":"1523.45ms"}
```

## Custom Middleware

You can add your own middleware to either bus:

```php
use Nactu\CommandBusInterface;

public function boot(CommandBusInterface $bus): void
{
    $bus->addMiddleware(function (object $command, callable $next): mixed {
        // Before handler
        Log::info('Dispatching command', ['command' => $command::class]);

        $result = $next($command);

        // After handler
        Log::info('Command dispatched', ['command' => $command::class]);

        return $result;
    });
}
```

### Database Transaction Middleware

A common pattern for commands:

```php
use Illuminate\Support\Facades\DB;

$bus->addMiddleware(function (object $command, callable $next): mixed {
    return DB::transaction(fn () => $next($command));
});
```

### Authorization Middleware

```php
$bus->addMiddleware(function (object $command, callable $next): mixed {
    if (method_exists($command, 'authorize')) {
        $command->authorize();
    }

    return $next($command);
});
```

## Disabling Built-in Middleware

Set the corresponding config flag to `false`:

```php
// config/nactu.php
'retry' => ['enabled' => false],
'performance' => ['enabled' => false],
'cache' => ['enabled' => false],
```
