<?php

declare(strict_types=1);

namespace Nactu;

interface QueryBusInterface
{
    public function dispatch(object $query): mixed;

    /**
     * Register a handler for a query
     *
     * @param  class-string  $queryClass
     * @param  class-string  $handlerClass
     */
    public function register(string $queryClass, string $handlerClass): void;

    public function addMiddleware(callable $middleware): void;
}
