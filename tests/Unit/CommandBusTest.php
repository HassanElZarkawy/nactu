<?php

declare(strict_types=1);

namespace Nactu\Tests\Unit;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Nactu\CommandBus;
use Nactu\CommandBusInterface;
use Nactu\Services\BusDispatcher;
use Nactu\Tests\Fixtures\Commands\CreateUser\CreateUserAltHandler;
use Nactu\Tests\Fixtures\Commands\CreateUser\CreateUserCommand;
use Nactu\Tests\Fixtures\CommandsFoo\OrphanCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CommandBusTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container;
    }

    private function createCommandBus(bool $autoDiscover = false): CommandBus
    {
        return new CommandBus(
            new BusDispatcher(
                $this->container,
                $autoDiscover,
                'App\\Commands',
                'App\\Commands',
                'Command',
            ),
        );
    }

    private function createAutoDiscoveringCommandBus(): CommandBus
    {
        return new CommandBus(
            new BusDispatcher(
                $this->container,
                true,
                'Nactu\\Tests\\Fixtures\\Commands',
                'Nactu\\Tests\\Fixtures\\Commands',
                'Command',
            ),
        );
    }

    #[Test]
    public function it_dispatches_command_to_registered_handler(): void
    {
        $command = new class {};

        $handler = new class
        {
            public function handle(object $command): string
            {
                return 'executed';
            }
        };

        $bus = $this->createCommandBus();
        $bus->register($command::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $result = $bus->dispatch($command);

        $this->assertSame('executed', $result);
    }

    #[Test]
    public function it_throws_when_no_handler_registered(): void
    {
        $command = new class {};
        $commandClass = $command::class;
        $bus = $this->createCommandBus();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No handler registered for Command: $commandClass");

        $bus->dispatch($command);
    }

    #[Test]
    public function it_calls_invoke_if_handle_method_does_not_exist(): void
    {
        $command = new class {};

        $handler = new class
        {
            public function __invoke(object $command): string
            {
                return 'invoked';
            }
        };

        $bus = $this->createCommandBus();
        $bus->register($command::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $result = $bus->dispatch($command);

        $this->assertSame('invoked', $result);
    }

    #[Test]
    public function it_executes_middleware_in_correct_order(): void
    {
        $command = new class {};
        $log = [];

        $handler = new class
        {
            public function handle(object $command): void {}
        };

        $bus = $this->createCommandBus();
        $bus->register($command::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $bus->addMiddleware(function (object $command, callable $next) use (&$log): mixed {
            $log[] = 'retry_before';
            $result = $next($command);
            $log[] = 'retry_after';

            return $result;
        });

        $bus->addMiddleware(function (object $command, callable $next) use (&$log): mixed {
            $log[] = 'performance_before';
            $result = $next($command);
            $log[] = 'performance_after';

            return $result;
        });

        $bus->dispatch($command);

        $this->assertSame([
            'retry_before',
            'performance_before',
            'performance_after',
            'retry_after',
        ], $log);
    }

    #[Test]
    public function it_passes_command_data_to_handler(): void
    {
        $command = new class('Hassan')
        {
            public function __construct(public string $name) {}
        };

        $handler = new class
        {
            public function handle(object $command): string
            {
                return 'Created: '.(property_exists($command, 'name') ? $command->name : '');
            }
        };

        $bus = $this->createCommandBus();
        $bus->register($command::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $result = $bus->dispatch($command);

        $this->assertSame('Created: Hassan', $result);
    }

    #[Test]
    public function it_implements_command_bus_interface(): void
    {
        $bus = $this->createCommandBus();

        $this->assertInstanceOf(CommandBusInterface::class, $bus);
    }

    #[Test]
    public function it_caches_handler_resolution_on_repeated_dispatches(): void
    {
        $command = new class {};

        $handler = new class
        {
            public int $count = 0;

            public function handle(object $command): int
            {
                return ++$this->count;
            }
        };

        $bus = $this->createCommandBus();
        $bus->register($command::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $this->assertSame(1, $bus->dispatch($command));
        $this->assertSame(2, $bus->dispatch($command));
        $this->assertSame(3, $bus->dispatch($command));
    }

    #[Test]
    public function middleware_can_transform_result(): void
    {
        $command = new class {};

        $handler = new class
        {
            public function handle(object $command): string
            {
                return 'created';
            }
        };

        $bus = $this->createCommandBus();
        $bus->register($command::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $bus->addMiddleware(function (object $command, callable $next): mixed {
            return ['status' => $next($command)];
        });

        $result = $bus->dispatch($command);

        $this->assertSame(['status' => 'created'], $result);
    }

    #[Test]
    public function middleware_can_short_circuit_without_reaching_handler(): void
    {
        $command = new class {};

        $handler = new class
        {
            public function handle(object $command): string
            {
                return 'should_not_reach';
            }
        };

        $bus = $this->createCommandBus();
        $bus->register($command::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $bus->addMiddleware(function (object $command, callable $next): mixed {
            return 'blocked';
        });

        $this->assertSame('blocked', $bus->dispatch($command));
    }

    #[Test]
    public function it_handles_different_commands_independently(): void
    {
        $commandA = new class {};
        $commandB = new class {};

        $handlerA = new class
        {
            public function handle(object $command): string
            {
                return 'A';
            }
        };

        $handlerB = new class
        {
            public function handle(object $command): string
            {
                return 'B';
            }
        };

        $bus = $this->createCommandBus();
        $bus->register($commandA::class, $handlerA::class);
        $bus->register($commandB::class, $handlerB::class);
        $this->container->instance($handlerA::class, $handlerA);
        $this->container->instance($handlerB::class, $handlerB);

        $this->assertSame('A', $bus->dispatch($commandA));
        $this->assertSame('B', $bus->dispatch($commandB));
    }

    #[Test]
    public function middleware_can_modify_the_command_passed_to_the_next_layer(): void
    {
        $command = new class {};

        $handler = new class
        {
            public function handle(object $command): string
            {
                return property_exists($command, 'name') ? $command->name : 'missing';
            }
        };

        $bus = $this->createCommandBus();
        $bus->register($command::class, $handler::class);
        $this->container->instance($handler::class, $handler);

        $bus->addMiddleware(function (object $command, callable $next): mixed {
            return $next(new class
            {
                public string $name = 'Modified';
            });
        });

        $this->assertSame('Modified', $bus->dispatch($command));
    }

    #[Test]
    public function it_auto_discovers_handlers_by_convention(): void
    {
        $bus = $this->createAutoDiscoveringCommandBus();

        $this->assertSame('created:Hassan', $bus->dispatch(new CreateUserCommand('Hassan')));
    }

    #[Test]
    public function it_throws_when_auto_discovery_namespace_boundary_does_not_match(): void
    {
        $bus = $this->createAutoDiscoveringCommandBus();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No handler registered for Command: '.OrphanCommand::class);

        $bus->dispatch(new OrphanCommand);
    }

    #[Test]
    public function it_prefers_manually_registered_handlers_over_discovered_ones(): void
    {
        $bus = $this->createAutoDiscoveringCommandBus();
        $bus->register(CreateUserCommand::class, CreateUserAltHandler::class);

        $this->assertSame('manual', $bus->dispatch(new CreateUserCommand('Hassan')));
    }

    #[Test]
    public function handler_instances_are_transient_by_default_and_singleton_when_enabled(): void
    {
        CountingHandler::$constructions = 0;

        $transient = new CommandBus(
            new BusDispatcher($this->container, false, 'App\\Commands', 'App\\Commands', 'Command'),
        );
        $transient->register(CountingCommand::class, CountingHandler::class);
        $transient->dispatch(new CountingCommand);
        $transient->dispatch(new CountingCommand);

        $this->assertSame(2, CountingHandler::$constructions);

        CountingHandler::$constructions = 0;

        $singleton = new CommandBus(
            new BusDispatcher($this->container, false, 'App\\Commands', 'App\\Commands', 'Command', true),
        );
        $singleton->register(CountingCommand::class, CountingHandler::class);
        $singleton->dispatch(new CountingCommand);
        $singleton->dispatch(new CountingCommand);

        $this->assertSame(1, CountingHandler::$constructions);
    }

    #[Test]
    public function it_throws_when_handler_has_neither_handle_nor_invoke(): void
    {
        $bus = $this->createCommandBus();
        $bus->register(NoInvokationCommand::class, NoInvokationHandler::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Handler [%s] must define a handle() or __invoke() method.',
            NoInvokationHandler::class,
        ));

        $bus->dispatch(new NoInvokationCommand);
    }
}

final class CountingCommand {}

final class CountingHandler
{
    public static int $constructions = 0;

    public function __construct()
    {
        self::$constructions++;
    }

    public function handle(CountingCommand $command): string
    {
        return 'ok';
    }
}

final class NoInvokationCommand {}

final class NoInvokationHandler {}
