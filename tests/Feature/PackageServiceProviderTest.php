<?php

declare(strict_types=1);

namespace Nactu\Tests\Feature;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Nactu\CommandBus;
use Nactu\CommandBusInterface;
use Nactu\Middlewares\CacheQueryMiddleware;
use Nactu\Middlewares\CommandRetrier;
use Nactu\Middlewares\PerformanceMonitor;
use Nactu\Providers\PackageServiceProvider;
use Nactu\QueryBus;
use Nactu\QueryBusInterface;
use Nactu\Services\CacheInvalidationService;
use Nactu\Services\CacheKeyGenerator;
use Nactu\Tests\Fixtures\Commands\CreateUser\CreateUserCommand;
use Nactu\Tests\Fixtures\Commands\SendInvoice\SendInvoiceCommand;
use Nactu\Tests\Fixtures\Queries\CacheableListUsers\CacheableListUsersHandler;
use Nactu\Tests\Fixtures\Queries\CacheableListUsers\CacheableListUsersQuery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PackageServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('cache.default', 'array');
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('nactu.auto_discover.commands_namespace', 'Nactu\Tests\Fixtures\Commands');
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('nactu.auto_discover.handlers_namespace', 'Nactu\Tests\Fixtures\Commands');
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('nactu.auto_discover.queries_namespace', 'Nactu\Tests\Fixtures\Queries');
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('nactu.auto_discover.query_handlers_namespace', 'Nactu\Tests\Fixtures\Queries');
        $app->make(\Illuminate\Contracts\Config\Repository::class)->set('nactu.retry.max_backoff', 0);
    }

    #[Test]
    public function it_binds_the_buses_as_singletons(): void
    {
        $commandBus = $this->app->make(CommandBusInterface::class);

        $this->assertInstanceOf(CommandBus::class, $commandBus);
        $this->assertSame($commandBus, $this->app->make(CommandBusInterface::class));

        $queryBus = $this->app->make(QueryBusInterface::class);

        $this->assertInstanceOf(QueryBus::class, $queryBus);
        $this->assertSame($queryBus, $this->app->make(QueryBusInterface::class));
    }

    #[Test]
    public function it_declares_the_provided_services(): void
    {
        $provider = new PackageServiceProvider($this->app);

        $this->assertSame([
            CommandBusInterface::class,
            QueryBusInterface::class,
            CacheKeyGenerator::class,
            CacheQueryMiddleware::class,
            CommandRetrier::class,
            PerformanceMonitor::class,
            CacheInvalidationService::class,
        ], $provider->provides());
    }

    #[Test]
    public function it_dispatches_commands_with_auto_discovery(): void
    {
        $bus = $this->app->make(CommandBusInterface::class);

        $this->assertSame('created:Hassan', $bus->dispatch(new CreateUserCommand('Hassan')));
    }

    #[Test]
    public function it_retries_failed_commands_before_succeeding(): void
    {
        $bus = $this->app->make(CommandBusInterface::class);

        $this->assertSame('sent', $bus->dispatch(new SendInvoiceCommand));
    }

    #[Test]
    public function it_caches_cacheable_queries_under_the_configured_prefix(): void
    {
        CacheableListUsersHandler::$executions = 0;

        $bus = $this->app->make(QueryBusInterface::class);
        $query = new CacheableListUsersQuery;

        $bus->dispatch($query);
        $bus->dispatch($query);

        $this->assertSame(1, CacheableListUsersHandler::$executions);

        $expectedKey = $this->app->make(CacheKeyGenerator::class)->generateFromCommand($query, 'nactu');

        $this->assertStringStartsWith('nactu:', $expectedKey);
        $this->assertSame('users:active', Cache::store()->get($expectedKey));
    }

    #[Test]
    public function it_uses_the_configured_cache_store(): void
    {
        $this->app->make(\Illuminate\Contracts\Config\Repository::class)->set('nactu.cache.store', 'array');

        $bus = $this->app->make(QueryBusInterface::class);
        $query = new CacheableListUsersQuery;

        $bus->dispatch($query);

        $expectedKey = $this->app->make(CacheKeyGenerator::class)->generateFromCommand($query, 'nactu');

        $this->assertSame('users:active', Cache::store('array')->get($expectedKey));
    }

    #[Test]
    public function it_skips_caching_when_disabled(): void
    {
        $this->app->make(\Illuminate\Contracts\Config\Repository::class)->set('nactu.cache.enabled', false);

        $bus = $this->app->make(QueryBusInterface::class);
        $query = new CacheableListUsersQuery;

        $bus->dispatch($query);

        $expectedKey = $this->app->make(CacheKeyGenerator::class)->generateFromCommand($query, 'nactu');

        $this->assertNull(Cache::store()->get($expectedKey));
    }

    #[Test]
    public function it_resolves_the_cache_invalidation_service(): void
    {
        $service = $this->app->make(CacheInvalidationService::class);

        $this->assertInstanceOf(CacheInvalidationService::class, $service);

        /** @var Repository $store */
        $store = Cache::store();

        $store->tags(['users'])->put('tagged_entry', 'value', 60);
        $service->invalidateByTags(['users']);
        $this->assertNull($store->get('tagged_entry'));

        $store->put('plain_entry', 'value', 60);
        $service->invalidateKeys('plain_entry');
        $this->assertNull($store->get('plain_entry'));

        $store->put('another_entry', 'value', 60);
        $service->clearAll();
        $this->assertNull($store->get('another_entry'));

        $warmed = $service->warm('warm_key', fn (): string => 'warmed', 60);
        $this->assertSame('warmed', $warmed);
        $this->assertSame('warmed', $store->get('warm_key'));

        $store->put('not_tagged', 'value', 60);

        $service->invalidateByTags([]);

        $this->assertSame('value', $store->get('not_tagged'));

        $warmedTagged = $service->warm('warmed_tagged', fn (): string => 'warmed', 60, ['products']);
        $this->assertSame('warmed', $warmedTagged);
        $this->assertSame('warmed', $store->tags(['products'])->get('warmed_tagged'));

        $service->invalidateByTags(['products']);
        $this->assertNull($store->tags(['products'])->get('warmed_tagged'));
    }
}
