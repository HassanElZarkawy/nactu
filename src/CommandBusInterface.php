<?php

declare(strict_types=1);

namespace Nactu;

interface CommandBusInterface
{
    /**
     * Dispatch a command to its handler
     */
    public function dispatch(object $command): mixed;

    /**
     * Register a handler for a command
     */
    public function register(string $commandClass, string $handlerClass): void;

    /**
     * Add middleware to the command bus
     */
    public function addMiddleware(callable $middleware): void;
}
