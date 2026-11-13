<?php

declare(strict_types=1);

namespace Nactu;

use Nactu\Services\BusDispatcher;

readonly class CommandBus implements CommandBusInterface
{
    public function __construct(
        private BusDispatcher $dispatcher,
    ) {}

    public function dispatch(object $command): mixed
    {
        return $this->dispatcher->dispatch($command);
    }

    /**
     * @param  class-string  $commandClass
     * @param  class-string  $handlerClass
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->dispatcher->register($commandClass, $handlerClass);
    }

    public function addMiddleware(callable $middleware): void
    {
        $this->dispatcher->addMiddleware($middleware);
    }
}
