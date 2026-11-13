<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

return [

    'auto_discover' => [
        'enabled' => (bool) env('NACTU_AUTO_DISCOVER_ENABLED', true),

        'commands_namespace' => env('NACTU_AUTO_DISCOVER_COMMANDS_NAMESPACE', 'App\\Commands'),

        'handlers_namespace' => env('NACTU_AUTO_DISCOVER_HANDLERS_NAMESPACE', 'App\\Commands'),

        'queries_namespace' => env('NACTU_AUTO_DISCOVER_QUERIES_NAMESPACE', 'App\\Queries'),

        'query_handlers_namespace' => env('NACTU_AUTO_DISCOVER_QUERY_HANDLERS_NAMESPACE', 'App\\Queries'),
    ],

    'handlers' => [
        // Reuse one handler instance per handler class across dispatches
        // (handlers effectively become container singletons). Reduces
        // per-dispatch construction cost, but stateful handlers will keep
        // their state between dispatches.
        'singleton' => (bool) env('NACTU_HANDLERS_SINGLETON', false),
    ],

    'cache' => [
        'enabled' => (bool) env('NACTU_CACHE_ENABLED', true),

        'store' => env('NACTU_CACHE_STORE', null),

        'prefix' => env('NACTU_CACHE_PREFIX', 'nactu'),

        'default_ttl' => (int) env('NACTU_CACHE_DEFAULT_TTL', 300),
    ],

    'retry' => [
        'enabled' => (bool) env('NACTU_RETRY_ENABLED', true),

        'max_attempts' => (int) env('NACTU_RETRY_MAX_ATTEMPTS', 3),

        'max_backoff' => (int) env('NACTU_RETRY_MAX_BACKOFF', 3),

        // Exceptions that should never be retried
        'except' => [
            ValidationException::class,
            ModelNotFoundException::class,
        ],
    ],

    'performance' => [
        'enabled' => (bool) env('NACTU_PERFORMANCE_ENABLED', true),

        'slow_threshold' => (float) env('NACTU_PERFORMANCE_SLOW_THRESHOLD', 1.0),
    ],

    'cache_key' => [
        'separator' => env('NACTU_CACHE_KEY_SEPARATOR', ':'),
    ],

];
