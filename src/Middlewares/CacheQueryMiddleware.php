<?php

declare(strict_types=1);

namespace Nactu\Middlewares;

use Illuminate\Cache\Repository as CacheRepository;
use Nactu\Contracts\CacheableQuery;
use Nactu\Services\CacheKeyGenerator;

/**
 * Middleware for caching query commands
 */
readonly class CacheQueryMiddleware
{
    public function __construct(
        private CacheRepository $cache,
        private CacheKeyGenerator $keyGenerator,
        private int $defaultTtl = 300,
        private ?string $prefix = null,
    ) {}

    /**
     * Handle the command through caching middleware
     */
    public function __invoke(object $command, callable $next): mixed
    {
        if (! $command instanceof CacheableQuery || $command->shouldBypassCache()) {
            return $next($command);
        }

        $cacheKey = $this->keyGenerator->generateFromCommand($command, $this->prefix);
        $tags = $command->getCacheTags();
        $ttl = $command->getCacheTtl() ?? $this->defaultTtl;

        $cacheStore = $tags === [] ? $this->cache : $this->cache->tags($tags);

        return $cacheStore->remember(
            $cacheKey,
            $ttl,
            fn (): mixed => $next($command)
        );
    }
}
