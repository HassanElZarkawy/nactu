<?php

declare(strict_types=1);

namespace Nactu;

interface QueryBusInterface
{
    public function dispatch(object $query): mixed;

    public function register(string $queryClass, string $handlerClass): void;

    public function addMiddleware(callable $middleware): void;
}
