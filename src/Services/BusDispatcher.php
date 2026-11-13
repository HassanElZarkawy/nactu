<?php

declare(strict_types=1);

namespace Nactu\Services;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;

class BusDispatcher
{
    /** @var array<class-string, class-string> */
    protected array $handlers = [];

    /** @var array<class-string, 'handle'|'__invoke'> */
    protected array $invokableMethods = [];

    /** @var array<class-string, object> */
    protected array $resolvedHandlers = [];

    /** @var list<callable> */
    protected array $middleware = [];

    protected ?Closure $cachedPipeline = null;

    protected int $middlewareVersion = 0;

    protected int $cachedVersion = -1;

    protected bool $autoDiscover;

    protected string $sourceNamespace;

    protected string $handlerNamespace;

    protected string $suffix;

    protected bool $singletonHandlers;

    public function __construct(
        protected Container $container,
        bool $autoDiscover = false,
        string $sourceNamespace = 'App\\Commands',
        string $handlerNamespace = 'App\\Handlers',
        string $suffix = 'Command',
        bool $singletonHandlers = false,
    ) {
        $this->autoDiscover = $autoDiscover;
        $this->sourceNamespace = $sourceNamespace;
        $this->handlerNamespace = $handlerNamespace;
        $this->suffix = $suffix;
        $this->singletonHandlers = $singletonHandlers;
    }

    /**
     * @throws BindingResolutionException
     */
    public function dispatch(object $command): mixed
    {
        $commandClass = $command::class;

        // Class-string resolution is eager (cheap, cached, and throws early so
        // the middleware never retries an unresolvable handler), but handler
        // construction is lazy: middleware that short-circuits — most notably
        // a cache hit — must not pay for building the handler object.
        $handlerClass = $this->resolveHandlerClass($commandClass);
        $invokableMethod = $this->resolveInvokableMethod($handlerClass);

        $dispatcher = $this;

        $resolvedHandler = null;

        $handler = function (object $command) use ($dispatcher, $handlerClass, $invokableMethod, &$resolvedHandler): mixed {
            $instance = $resolvedHandler ??= $dispatcher->makeHandler($handlerClass);

            // The invokable method is validated per handler class by
            // resolveInvokableMethod(); phpstan cannot see user handler contracts.
            // @phpstan-ignore-next-line
            return $instance->{$invokableMethod}($command);
        };

        return $this->getPipeline()($command, $handler);
    }

    /**
     * @param  class-string  $commandClass
     * @param  class-string  $handlerClass
     */
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
     * Resolve the handler class for the given command class.
     *
     * @param  class-string  $commandClass
     * @return class-string
     */
    protected function resolveHandlerClass(string $commandClass): string
    {
        $handlerClass = $this->handlers[$commandClass] ?? null;

        if ($handlerClass === null) {
            $handlerClass = $this->discoverHandler($commandClass);

            if ($handlerClass === null) {
                throw new InvalidArgumentException("No handler registered for $this->suffix: $commandClass");
            }

            $this->handlers[$commandClass] = $handlerClass;
        }

        return $handlerClass;
    }

    /**
     * Resolve which method to call on the handler, cached per handler class.
     *
     * @param  class-string  $handlerClass
     * @return 'handle'|'__invoke'
     */
    protected function resolveInvokableMethod(string $handlerClass): string
    {
        return $this->invokableMethods[$handlerClass] ??= $this->detectInvokableMethod($handlerClass);
    }

    /**
     * @param  class-string  $handlerClass
     * @return 'handle'|'__invoke'
     */
    private function detectInvokableMethod(string $handlerClass): string
    {
        if (method_exists($handlerClass, 'handle')) {
            return 'handle';
        }

        if (method_exists($handlerClass, '__invoke')) {
            return '__invoke';
        }

        throw new InvalidArgumentException("Handler [$handlerClass] must define a handle() or __invoke() method.");
    }

    /**
     * Construct (or reuse, under handlers.singleton) the handler instance.
     *
     * @param  class-string  $handlerClass
     *
     * @throws BindingResolutionException
     */
    protected function makeHandler(string $handlerClass): object
    {
        if ($this->singletonHandlers) {
            return $this->resolvedHandlers[$handlerClass] ??= $this->container->make($handlerClass);
        }

        return $this->container->make($handlerClass);
    }

    /**
     * @param  class-string  $commandClass
     * @return class-string|null
     */
    protected function discoverHandler(string $commandClass): ?string
    {
        if (! $this->autoDiscover) {
            return null;
        }

        $prefix = $this->sourceNamespace.'\\';

        if (! str_starts_with($commandClass, $prefix)) {
            return null;
        }

        $relativeClass = substr($commandClass, strlen($prefix));

        $handlerClass = $this->handlerNamespace.'\\'.(string) preg_replace(
            '/'.preg_quote($this->suffix, '/').'$/',
            'Handler',
            $relativeClass,
        );

        return class_exists($handlerClass) ? $handlerClass : null;
    }

    protected function getPipeline(): Closure
    {
        if ($this->cachedPipeline !== null && $this->cachedVersion === $this->middlewareVersion) {
            return $this->cachedPipeline;
        }

        $this->cachedPipeline = array_reduce(
            array_reverse($this->middleware),
            fn (Closure $next, callable $middleware): Closure => fn (object $command, callable $handler): mixed => $middleware(
                $command,
                fn (?object $override = null): mixed => $next($override ?? $command, $handler),
            ),
            fn (object $command, callable $handler): mixed => $handler($command),
        );

        $this->cachedVersion = $this->middlewareVersion;

        return $this->cachedPipeline;
    }
}
