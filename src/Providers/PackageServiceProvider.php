<?php

declare(strict_types=1);

namespace Nactu\Providers;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use Nactu\CommandBus;
use Nactu\CommandBusInterface;
use Nactu\Console\MakeCommandCommand;
use Nactu\Console\MakeQueryCommand;
use Nactu\Middlewares\CacheQueryMiddleware;
use Nactu\Middlewares\CommandRetrier;
use Nactu\Middlewares\PerformanceMonitor;
use Nactu\QueryBus;
use Nactu\QueryBusInterface;
use Nactu\Services\BusDispatcher;
use Nactu\Services\CacheInvalidationService;
use Nactu\Services\CacheKeyGenerator;
use Psr\Log\LoggerInterface;

final class PackageServiceProvider extends ServiceProvider
{
    protected string $configKey = 'nactu';

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/nactu.php', $this->configKey,
        );

        // Middleware is attached inside the singleton factories so the stack
        // survives container flushes (e.g. under Laravel Octane)
        $this->app->singleton(CommandBusInterface::class, function ($app): CommandBus {
            $dispatcher = new BusDispatcher(
                $app,
                $app['config']->get("$this->configKey.auto_discover.enabled", true),
                $app['config']->get("$this->configKey.auto_discover.commands_namespace", 'App\\Commands'),
                $app['config']->get("$this->configKey.auto_discover.handlers_namespace", 'App\\Commands'),
                'Command',
                $app['config']->get("$this->configKey.handlers.singleton", false),
            );

            if ($app['config']->get("$this->configKey.retry.enabled", true)) {
                $dispatcher->addMiddleware($app->make(CommandRetrier::class));
            }

            if ($app['config']->get("$this->configKey.performance.enabled", true)) {
                $dispatcher->addMiddleware($app->make(PerformanceMonitor::class));
            }

            return new CommandBus($dispatcher);
        });

        $this->app->singleton(QueryBusInterface::class, function ($app): QueryBus {
            $dispatcher = new BusDispatcher(
                $app,
                $app['config']->get("$this->configKey.auto_discover.enabled", true),
                $app['config']->get("$this->configKey.auto_discover.queries_namespace", 'App\\Queries'),
                $app['config']->get("$this->configKey.auto_discover.query_handlers_namespace", 'App\\Queries'),
                'Query',
                $app['config']->get("$this->configKey.handlers.singleton", false),
            );

            if ($app['config']->get("$this->configKey.cache.enabled", true)) {
                $dispatcher->addMiddleware($app->make(CacheQueryMiddleware::class));
            }

            if ($app['config']->get("$this->configKey.performance.enabled", true)) {
                $dispatcher->addMiddleware($app->make(PerformanceMonitor::class));
            }

            return new QueryBus($dispatcher);
        });

        $this->app->singleton(CacheKeyGenerator::class, fn ($app): CacheKeyGenerator => new CacheKeyGenerator(
            $app['config']->get("$this->configKey.cache_key.separator", ':'),
        ));

        $this->app->singleton(CacheQueryMiddleware::class, function ($app): CacheQueryMiddleware {
            /** @var CacheManager $cacheManager */
            $cacheManager = $app->make('cache');

            /** @var CacheRepository $cacheRepository */
            $cacheRepository = $cacheManager->store($app['config']->get("$this->configKey.cache.store"));

            return new CacheQueryMiddleware(
                $cacheRepository,
                $app->make(CacheKeyGenerator::class),
                $app['config']->get("$this->configKey.cache.default_ttl", 300),
                $app['config']->get("$this->configKey.cache.prefix"),
            );
        });

        $this->app->singleton(CommandRetrier::class, fn ($app): CommandRetrier => new CommandRetrier(
            $app['config']->get("$this->configKey.retry.max_attempts", 3),
            $app['config']->get("$this->configKey.retry.max_backoff", 3),
            $app['config']->get("$this->configKey.retry.except", []),
            $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(PerformanceMonitor::class, fn ($app): PerformanceMonitor => new PerformanceMonitor(
            $app['config']->get("$this->configKey.performance.slow_threshold", 1.0),
            $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(CacheInvalidationService::class, function ($app): CacheInvalidationService {
            /** @var CacheManager $cacheManager */
            $cacheManager = $app->make('cache');

            return new CacheInvalidationService(
                $cacheManager,
                $app['config']->get("$this->configKey.cache.store"),
                $app['config']->get("$this->configKey.cache.default_ttl", 300),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/nactu.php' => $this->app->configPath('nactu.php'),
        ], 'nactu-config');

        $this->commands([
            MakeCommandCommand::class,
            MakeQueryCommand::class,
        ]);
    }

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            CommandBusInterface::class,
            QueryBusInterface::class,
            CacheKeyGenerator::class,
            CacheQueryMiddleware::class,
            CommandRetrier::class,
            PerformanceMonitor::class,
            CacheInvalidationService::class,
        ];
    }
}
