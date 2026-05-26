<?php

return [

    'auto_discover' => [
        'enabled' => (bool) env('NACTU_AUTO_DISCOVER_ENABLED', true),

        'commands_namespace' => env('NACTU_AUTO_DISCOVER_COMMANDS_NAMESPACE', 'App\\Commands'),

        'handlers_namespace' => env('NACTU_AUTO_DISCOVER_HANDLERS_NAMESPACE', 'App\\Handlers'),

        'queries_namespace' => env('NACTU_AUTO_DISCOVER_QUERIES_NAMESPACE', 'App\\Queries'),

        'query_handlers_namespace' => env('NACTU_AUTO_DISCOVER_QUERY_HANDLERS_NAMESPACE', 'App\\QueryHandlers'),
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
    ],

    'performance' => [
        'enabled' => (bool) env('NACTU_PERFORMANCE_ENABLED', true),

        'slow_threshold' => (float) env('NACTU_PERFORMANCE_SLOW_THRESHOLD', 1.0),
    ],

    'cache_key' => [
        'separator' => env('NACTU_CACHE_KEY_SEPARATOR', ':'),
    ],

];
