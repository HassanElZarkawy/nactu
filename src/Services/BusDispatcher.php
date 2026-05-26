<?php

declare(strict_types=1);

namespace Nactu\Services;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;

class BusDispatcher
{
    protected array $handlers = [];

    protected array $middleware = [];

    protected ?Closure $cachedPipeline = null;

    protected int $middlewareVersion = 0;

    protected int $cachedVersion = -1;

    protected bool $autoDiscover;

    protected string $sourceNamespace;

    protected string $handlerNamespace;

    protected string $suffix;

    public function __construct(
        protected Container $container,
        bool $autoDiscover = false,
        string $sourceNamespace = 'App\\Commands',
        string $handlerNamespace = 'App\\Handlers',
        string $suffix = 'Command',
    ) {
        $this->autoDiscover = $autoDiscover;
        $this->sourceNamespace = $sourceNamespace;
        $this->handlerNamespace = $handlerNamespace;
        $this->suffix = $suffix;
    }

    /**
     * @throws BindingResolutionException
     */
    public function dispatch(object $command): mixed
    {
        $handler = $this->resolveHandler($command);

        return $this->getPipeline()($command, $handler);
    }

    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
        $this->middlewareVersion++;
    }

    /**
     * @throws BindingResolutionException
     */
    protected function resolveHandler(object $command): callable
    {
        $commandClass = $command::class;
        $handlerClass = $this->handlers[$commandClass] ??= $this->discoverHandler($commandClass);

        if ($handlerClass === null) {
            throw new InvalidArgumentException("No handler registered for $this->suffix: $commandClass");
        }

        $handler = $this->container->make($handlerClass);

        return method_exists($handler, 'handle')
            ? fn ($command) => $handler->handle($command)
            : $handler;
    }

    protected function discoverHandler(string $commandClass): ?string
    {
        if (! $this->autoDiscover) {
            return null;
        }

        if (! str_starts_with($commandClass, $this->sourceNamespace)) {
            return null;
        }

        $handlerClass = str_replace($this->sourceNamespace, $this->handlerNamespace, $commandClass);
        $handlerClass = preg_replace('/'.$this->suffix.'$/', 'Handler', $handlerClass);

        return class_exists($handlerClass) ? $handlerClass : null;
    }

    protected function getPipeline(): Closure
    {
        if ($this->cachedPipeline !== null && $this->cachedVersion === $this->middlewareVersion) {
            return $this->cachedPipeline;
        }

        $this->cachedPipeline = array_reduce(
            array_reverse($this->middleware),
            fn (Closure $next, callable $middleware): Closure => fn (object $command, callable $handler) => $middleware($command, fn () => $next($command, $handler)),
            fn (object $command, callable $handler): mixed => $handler($command),
        );

        $this->cachedVersion = $this->middlewareVersion;

        return $this->cachedPipeline;
    }
}
