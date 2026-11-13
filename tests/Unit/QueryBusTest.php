<?php

declare(strict_types=1);

namespace Nactu\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Container\Container;
use InvalidArgumentException;
use Nactu\Middlewares\CacheQueryMiddleware;
use Nactu\QueryBus;
use Nactu\QueryBusInterface;
use Nactu\Services\BusDispatcher;
use Nactu\Services\CacheKeyGenerator;
use Nactu\Tests\Fixtures\Queries\GetUser\GetUserQuery;
use Nactu\Tests\Fixtures\Queries\Ttl\TtlHandler;
use Nactu\Tests\Fixtures\Queries\Ttl\TtlQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueryBusTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container;
    }

    private function createQueryBus(bool $autoDiscover = false): QueryBus
    {
        return new QueryBus(
            new BusDispatcher(
                $this->container,
                $autoDiscover,
                'App\\Queries',
                'App\\Queries',
                'Query',
            ),
        );
    }

    private function createAutoDiscoveringQueryBus(): QueryBus
    {
        return new QueryBus(
            new BusDispatcher(
                $this->container,
                true,
                'Nactu\\Tests\\Fixtures\\Queries',
                'Nactu\\Tests\\Fixtures\\Queries',
                'Query',
            ),
        );
    }

    #[Test]
    public function it_dispatches_query_to_registered_handler(): void
    {
        $query = new class {};

        $handler = new class
        {
            public function handle(object $query): string
            {
                return 'result';
            }
        };

        $bus = $this->createQueryBus();
        $bus->register($query::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $result = $bus->dispatch($query);

        $this->assertSame('result', $result);
    }

    #[Test]
    public function it_throws_when_no_handler_registered(): void
    {
        $query = new class {};
        $queryClass = $query::class;
        $bus = $this->createQueryBus();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No handler registered for Query: $queryClass");

        $bus->dispatch($query);
    }

    #[Test]
    public function it_calls_invoke_if_handle_method_does_not_exist(): void
    {
        $query = new class {};

        $handler = new class
        {
            public function __invoke(object $query): int
            {
                return 42;
            }
        };

        $bus = $this->createQueryBus();
        $bus->register($query::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $result = $bus->dispatch($query);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function it_executes_middleware_in_order(): void
    {
        $query = new class {};
        $log = [];

        $handler = new class
        {
            public function handle(object $query): string
            {
                return 'done';
            }
        };

        $bus = $this->createQueryBus();
        $bus->register($query::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $bus->addMiddleware(function (object $query, callable $next) use (&$log): mixed {
            $log[] = 'middleware_a_before';
            $result = $next($query);
            $log[] = 'middleware_a_after';

            return $result;
        });

        $bus->addMiddleware(function (object $query, callable $next) use (&$log): mixed {
            $log[] = 'middleware_b_before';
            $result = $next($query);
            $log[] = 'middleware_b_after';

            return $result;
        });

        $bus->dispatch($query);

        $this->assertSame([
            'middleware_a_before',
            'middleware_b_before',
            'middleware_b_after',
            'middleware_a_after',
        ], $log);
    }

    #[Test]
    public function it_passes_query_data_to_handler(): void
    {
        $query = new class('Hassan')
        {
            public function __construct(public string $name) {}
        };

        $handler = new class
        {
            public function handle(object $query): string
            {
                return 'Hello, '.(property_exists($query, 'name') ? $query->name : '');
            }
        };

        $bus = $this->createQueryBus();
        $bus->register($query::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $result = $bus->dispatch($query);

        $this->assertSame('Hello, Hassan', $result);
    }

    #[Test]
    public function it_caches_handler_resolution_on_repeated_dispatches(): void
    {
        $query = new class {};

        $handler = new class
        {
            public int $invocationCount = 0;

            public function handle(object $query): int
            {
                return ++$this->invocationCount;
            }
        };

        $bus = $this->createQueryBus();
        $bus->register($query::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $result1 = $bus->dispatch($query);
        $result2 = $bus->dispatch($query);

        $this->assertSame(1, $result1);
        $this->assertSame(2, $result2);
    }

    #[Test]
    public function it_implements_query_bus_interface(): void
    {
        $bus = $this->createQueryBus();

        $this->assertInstanceOf(QueryBusInterface::class, $bus);
    }

    #[Test]
    public function middleware_can_modify_result(): void
    {
        $query = new class {};

        $handler = new class
        {
            public function handle(object $query): string
            {
                return 'original';
            }
        };

        $bus = $this->createQueryBus();
        $bus->register($query::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $bus->addMiddleware(function (object $query, callable $next): mixed {
            return strtoupper($next($query));
        });

        $result = $bus->dispatch($query);

        $this->assertSame('ORIGINAL', $result);
    }

    #[Test]
    public function middleware_can_short_circuit(): void
    {
        $query = new class {};

        $handler = new class
        {
            public function handle(object $query): string
            {
                return 'from_handler';
            }
        };

        $bus = $this->createQueryBus();
        $bus->register($query::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $bus->addMiddleware(function (object $query, callable $next): mixed {
            return 'short_circuited';
        });

        $result = $bus->dispatch($query);

        $this->assertSame('short_circuited', $result);
    }

    #[Test]
    public function it_handles_different_queries_independently(): void
    {
        $queryA = new class {};
        $queryB = new class {};

        $handlerA = new class
        {
            public function handle(object $query): string
            {
                return 'A';
            }
        };

        $handlerB = new class
        {
            public function handle(object $query): string
            {
                return 'B';
            }
        };

        $bus = $this->createQueryBus();
        $bus->register($queryA::class, $handlerA::class);
        $bus->register($queryB::class, $handlerB::class);
        $this->container->instance($handlerA::class, $handlerA);
        $this->container->instance($handlerB::class, $handlerB);

        $this->assertSame('A', $bus->dispatch($queryA));
        $this->assertSame('B', $bus->dispatch($queryB));
    }

    #[Test]
    public function it_auto_discovers_handlers_by_convention(): void
    {
        $bus = $this->createAutoDiscoveringQueryBus();

        $this->assertSame('user:5', $bus->dispatch(new GetUserQuery(5)));
    }

    #[Test]
    public function it_does_not_construct_the_handler_on_cache_hits(): void
    {
        TtlHandler::$constructions = 0;

        $bus = new QueryBus(
            new BusDispatcher($this->container, false, 'App\\Queries', 'App\\Queries', 'Query'),
        );
        $bus->register(TtlQuery::class, TtlHandler::class);
        $bus->addMiddleware(new CacheQueryMiddleware(
            new CacheRepository(new ArrayStore),
            new CacheKeyGenerator(':'),
            300,
        ));

        $first = $bus->dispatch(new TtlQuery);

        $this->assertSame('ttl:600', $first);
        $this->assertSame($first, $bus->dispatch(new TtlQuery));

        $this->assertSame(1, TtlHandler::$constructions);
    }
}
