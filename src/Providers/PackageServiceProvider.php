<?php

declare(strict_types=1);

namespace Nactu\Providers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nactu\CommandBus;
use Nactu\CommandBusInterface;
use Nactu\Console\MakeCommandCommand;
use Nactu\Console\MakeHandlerCommand;
use Nactu\Middlewares\CacheQueryMiddleware;
use Nactu\Middlewares\CommandRetrier;
use Nactu\Middlewares\PerformanceMonitor;
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

        $this->app->singleton(CommandBusInterface::class, fn ($app): CommandBus => new CommandBus(
            $app,
            $app['config']->get("{$this->configKey}.auto_discover.enabled", true),
            $app['config']->get("{$this->configKey}.auto_discover.commands_namespace", 'App\\Commands'),
            $app['config']->get("{$this->configKey}.auto_discover.handlers_namespace", 'App\\Handlers'),
        ));

        $this->app->singleton(CacheKeyGenerator::class, fn ($app): CacheKeyGenerator => new CacheKeyGenerator(
            $app['config']->get("$this->configKey.cache_key.separator", ':'),
        ));

        $this->app->singleton(CacheInvalidationService::class, fn ($app): CacheInvalidationService => new CacheInvalidationService(
            $app->make(CacheRepository::class),
            $app->make(LoggerInterface::class),
        ));

        $this->app->singleton(CacheQueryMiddleware::class, fn ($app): CacheQueryMiddleware => new CacheQueryMiddleware(
            $app->make(CacheRepository::class),
            $app->make(CacheKeyGenerator::class),
            $app['config']->get("$this->configKey.cache.default_ttl", 300),
        ));

        $this->app->singleton(CommandRetrier::class, fn ($app): CommandRetrier => new CommandRetrier(
            $app['config']->get("$this->configKey.retry.max_attempts", 3),
            $app['config']->get("$this->configKey.retry.max_backoff", 3),
        ));

        $this->app->singleton(PerformanceMonitor::class, fn ($app): PerformanceMonitor => new PerformanceMonitor(
            $app['config']->get("$this->configKey.performance.slow_threshold", 1.0),
        ));
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/nactu.php' => $this->app->configPath('nactu.php'),
        ], 'nactu-config');

        $this->registerMiddleware();

        $this->commands([
            MakeCommandCommand::class,
            MakeHandlerCommand::class,
        ]);
    }

    /**
     * @throws BindingResolutionException
     */
    protected function registerMiddleware(): void
    {
        /** @var CommandBusInterface $commandBus */
        $commandBus = $this->app->make(CommandBusInterface::class);

        if ($this->app['config']->get("$this->configKey.cache.enabled", true)) {
            $commandBus->addMiddleware($this->app->make(CacheQueryMiddleware::class));
        }

        if ($this->app['config']->get("$this->configKey.performance.enabled", true)) {
            $commandBus->addMiddleware($this->app->make(PerformanceMonitor::class));
        }

        if ($this->app['config']->get("$this->configKey.retry.enabled", true)) {
            $commandBus->addMiddleware($this->app->make(CommandRetrier::class));
        }
    }

    public function provides(): array
    {
        return [CommandBusInterface::class];
    }
}
