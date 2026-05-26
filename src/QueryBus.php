<?php

declare(strict_types=1);

namespace Nactu;

use Nactu\Services\BusDispatcher;

readonly class QueryBus implements QueryBusInterface
{
    public function __construct(
        private BusDispatcher $dispatcher,
    ) {}

    public function dispatch(object $query): mixed
    {
        return $this->dispatcher->dispatch($query);
    }

    public function register(string $queryClass, string $handlerClass): void
    {
        $this->dispatcher->register($queryClass, $handlerClass);
    }

    public function addMiddleware(callable $middleware): void
    {
        $this->dispatcher->addMiddleware($middleware);
    }
}
