# Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=nactu-config
```

This creates `config/nactu.php`:

## Full Reference

```php
return [

    'auto_discover' => [
        // Enable or disable auto-discovery of handlers
        'enabled' => (bool) env('NACTU_AUTO_DISCOVER_ENABLED', true),

        // Namespace for command classes
        'commands_namespace' => env('NACTU_AUTO_DISCOVER_COMMANDS_NAMESPACE', 'App\\Commands'),

        // Namespace for command handler classes
        'handlers_namespace' => env('NACTU_AUTO_DISCOVER_HANDLERS_NAMESPACE', 'App\\Handlers'),

        // Namespace for query classes
        'queries_namespace' => env('NACTU_AUTO_DISCOVER_QUERIES_NAMESPACE', 'App\\Queries'),

        // Namespace for query handler classes
        'query_handlers_namespace' => env('NACTU_AUTO_DISCOVER_QUERY_HANDLERS_NAMESPACE', 'App\\QueryHandlers'),
    ],

    'cache' => [
        // Enable or disable query caching
        'enabled' => (bool) env('NACTU_CACHE_ENABLED', true),

        // Cache store to use (null = default store)
        'store' => env('NACTU_CACHE_STORE', null),

        // Cache key prefix
        'prefix' => env('NACTU_CACHE_PREFIX', 'nactu'),

        // Default TTL in seconds for cached queries
        'default_ttl' => (int) env('NACTU_CACHE_DEFAULT_TTL', 300),
    ],

    'retry' => [
        // Enable or disable command retry
        'enabled' => (bool) env('NACTU_RETRY_ENABLED', true),

        // Maximum retry attempts
        'max_attempts' => (int) env('NACTU_RETRY_MAX_ATTEMPTS', 3),

        // Maximum backoff in seconds between retries (console only)
        'max_backoff' => (int) env('NACTU_RETRY_MAX_BACKOFF', 3),
    ],

    'performance' => [
        // Enable or disable performance monitoring
        'enabled' => (bool) env('NACTU_PERFORMANCE_ENABLED', true),

        // Threshold in seconds — executions longer than this are logged
        'slow_threshold' => (float) env('NACTU_PERFORMANCE_SLOW_THRESHOLD', 1.0),
    ],

    'cache_key' => [
        // Separator used in generated cache keys
        'separator' => env('NACTU_CACHE_KEY_SEPARATOR', ':'),
    ],

];
```

## Environment Variables

| Variable                                       | Default             | Description                   |
|------------------------------------------------|---------------------|-------------------------------|
| `NACTU_AUTO_DISCOVER_ENABLED`                  | `true`              | Enable auto-discovery         |
| `NACTU_AUTO_DISCOVER_COMMANDS_NAMESPACE`       | `App\Commands`      | Commands namespace            |
| `NACTU_AUTO_DISCOVER_HANDLERS_NAMESPACE`       | `App\Handlers`      | Command handlers namespace    |
| `NACTU_AUTO_DISCOVER_QUERIES_NAMESPACE`        | `App\Queries`       | Queries namespace             |
| `NACTU_AUTO_DISCOVER_QUERY_HANDLERS_NAMESPACE` | `App\QueryHandlers` | Query handlers namespace      |
| `NACTU_CACHE_ENABLED`                          | `true`              | Enable query caching          |
| `NACTU_CACHE_STORE`                            | `null`              | Cache store (null = default)  |
| `NACTU_CACHE_PREFIX`                           | `nactu`             | Cache key prefix              |
| `NACTU_CACHE_DEFAULT_TTL`                      | `300`               | Default cache TTL (seconds)   |
| `NACTU_RETRY_ENABLED`                          | `true`              | Enable command retry          |
| `NACTU_RETRY_MAX_ATTEMPTS`                     | `3`                 | Max retry attempts            |
| `NACTU_RETRY_MAX_BACKOFF`                      | `3`                 | Max backoff (seconds)         |
| `NACTU_PERFORMANCE_ENABLED`                    | `true`              | Enable performance monitoring |
| `NACTU_PERFORMANCE_SLOW_THRESHOLD`             | `1.0`               | Slow threshold (seconds)      |
| `NACTU_CACHE_KEY_SEPARATOR`                    | `:`                 | Cache key separator           |
