#!/usr/bin/env php
<?php

/**
 * Nactu performance benchmark.
 *
 * Measures the hot dispatch paths end to end, plus cache-key generation in
 * isolation, so optimizations can be compared before/after. The script is
 * standalone (no Testbench, no database) and exercises the exact middleware
 * wiring the service provider uses.
 *
 * Usage:
 *   composer bench
 *   php benchmarks/bench.php
 *
 * Results are deterministic in shape (same fixtures, same iteration counts),
 * so outputs from different points in time are directly comparable.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use Nactu\CommandBus;
use Nactu\Contracts\CacheableQuery;
use Nactu\Middlewares\CacheQueryMiddleware;
use Nactu\Middlewares\CommandRetrier;
use Nactu\Middlewares\PerformanceMonitor;
use Nactu\QueryBus;
use Nactu\Services\BusDispatcher;
use Nactu\Services\CacheKeyGenerator;
use Psr\Log\NullLogger;

// ---------------------------------------------------------------------------
// Fixtures — plain DTOs and handlers, no framework magic
// ---------------------------------------------------------------------------

final class BenchException extends Exception {}

final class BenchListUsersQuery implements CacheableQuery
{
    public function __construct(
        public string $status = 'active',
        public int $page = 1,
        public int $perPage = 15,
        public string $search = '',
        public bool $withTrashed = false,
    ) {}

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

final class BenchListUsersHandler
{
    public function handle(BenchListUsersQuery $query): string
    {
        return 'users:'.$query->page;
    }
}

/** Unique id per instance → unique cache key → always a cache miss. */
final class BenchUniqueQuery implements CacheableQuery
{
    public function __construct(
        public int $id = 0,
    ) {}

    public function getCacheTtl(): ?int
    {
        return 60;
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

final class BenchUniqueHandler
{
    public function handle(BenchUniqueQuery $query): string
    {
        return 'item:'.$query->id;
    }
}

/** Not a CacheableQuery → the cache middleware passes straight through. */
final class BenchPlainQuery
{
    public function __construct(
        public int $page = 1,
        public string $sort = 'name',
        public string $direction = 'asc',
    ) {}
}

final class BenchPlainHandler
{
    public function handle(BenchPlainQuery $query): string
    {
        return 'plain';
    }
}

final class BenchCreateUserCommand
{
    public function __construct(
        public string $name = 'Hassan',
        public string $email = 'hassan@example.com',
    ) {}
}

final class BenchCreateUserHandler
{
    public function handle(BenchCreateUserCommand $command): string
    {
        return 'created';
    }
}

final class BenchFailingCommand {}

final class BenchFailingHandler
{
    public function handle(BenchFailingCommand $command): never
    {
        throw new BenchException('expected failure');
    }
}

// ---------------------------------------------------------------------------
// Minimal container — enough for logger() and app()->runningInConsole()
// ---------------------------------------------------------------------------

$container = new class extends Container
{
    public function runningInConsole(): bool
    {
        return false;
    }
};

Container::setInstance($container);
$container->instance('log', new NullLogger);
$container->alias('log', NullLogger::class);

// ---------------------------------------------------------------------------
// Wire the buses exactly like PackageServiceProvider does
// ---------------------------------------------------------------------------

$keyGenerator = new CacheKeyGenerator(':');
$cache = new CacheRepository(new ArrayStore);

$queryDispatcher = new BusDispatcher($container, false, 'Bench\Queries', 'Bench\Queries', 'Query');
$queryDispatcher->register(BenchListUsersQuery::class, BenchListUsersHandler::class);
$queryDispatcher->register(BenchUniqueQuery::class, BenchUniqueHandler::class);
$queryDispatcher->register(BenchPlainQuery::class, BenchPlainHandler::class);
$queryDispatcher->addMiddleware(new CacheQueryMiddleware($cache, $keyGenerator, 300, 'bench'));
$queryDispatcher->addMiddleware(new PerformanceMonitor);
$queryBus = new QueryBus($queryDispatcher);

$commandDispatcher = new BusDispatcher($container, false, 'Bench\Commands', 'Bench\Commands', 'Command');
$commandDispatcher->register(BenchCreateUserCommand::class, BenchCreateUserHandler::class);
$commandDispatcher->register(BenchFailingCommand::class, BenchFailingHandler::class);
$commandDispatcher->addMiddleware(new CommandRetrier(3, 0, [BenchException::class]));
$commandDispatcher->addMiddleware(new PerformanceMonitor);
$commandBus = new CommandBus($commandDispatcher);

$bareDispatcher = new BusDispatcher($container, false, 'Bench\Commands', 'Bench\Commands', 'Command');
$bareDispatcher->register(BenchCreateUserCommand::class, BenchCreateUserHandler::class);
$bareBus = new CommandBus($bareDispatcher);

// ---------------------------------------------------------------------------
// Harness
// ---------------------------------------------------------------------------

/**
 * @param  callable(int): mixed  $fn
 * @return array{string, int, float, float}
 */
function bench(string $name, int $iterations, callable $fn): array
{
    $warmup = min($iterations, 2000);

    for ($i = 0; $i < $warmup; $i++) {
        $fn($i);
    }

    gc_collect_cycles();

    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $fn($i);
    }

    $elapsedNs = hrtime(true) - $start;

    return [
        $name,
        $iterations,
        $elapsedNs / $iterations / 1000,        // µs per op
        $iterations / ($elapsedNs / 1e9),       // ops per second
    ];
}

$results = [];

// Pre-populate the cache so the hit case really measures hits.
$cachedQuery = new BenchListUsersQuery;
$queryBus->dispatch($cachedQuery);

$results[] = bench('cache_key_generation (isolated)', 100_000, fn (): string => $keyGenerator->generateFromCommand($cachedQuery, 'bench'));

$results[] = bench('query_cache_hit', 100_000, fn (): mixed => $queryBus->dispatch($cachedQuery));

$results[] = bench('query_cache_miss', 20_000, fn (int $i): mixed => $queryBus->dispatch(new BenchUniqueQuery($i)));

$results[] = bench('query_uncached (no CacheableQuery)', 100_000, fn (): mixed => $queryBus->dispatch(new BenchPlainQuery));

$results[] = bench('command_success (retry + monitor)', 100_000, fn (): mixed => $commandBus->dispatch(new BenchCreateUserCommand));

$results[] = bench('command_failure (non-retryable, logged)', 5_000, function () use ($commandBus): void {
    try {
        $commandBus->dispatch(new BenchFailingCommand);
    } catch (BenchException) {
        // expected
    }
});

$results[] = bench('dispatch_bare (no middleware)', 100_000, fn (): mixed => $bareBus->dispatch(new BenchCreateUserCommand));

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

$opcache = function_exists('opcache_get_status') && is_array(@opcache_get_status(false));

printf("Nactu benchmark — PHP %s%s | %s\n", PHP_VERSION, $opcache ? ' (opcache on)' : '', php_uname('s').' '.php_uname('m'));
printf("%-42s %10s %12s %14s\n", 'case', 'ops', 'µs/op', 'ops/sec');
printf("%s\n", str_repeat('-', 82));

foreach ($results as [$name, $iterations, $usPerOp, $opsPerSec]) {
    printf("%-42s %10s %12.3f %14s\n", $name, number_format($iterations), $usPerOp, number_format((int) $opsPerSec));
}
