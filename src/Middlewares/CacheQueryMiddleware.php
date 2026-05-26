<?php

namespace Nactu\Middlewares;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
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
        private int $defaultTtl = 300
    ) {}

    /**
     * Handle the command through caching middleware
     */
    public function __invoke(object $command, callable $next): mixed
    {
        if (! $command instanceof CacheableQuery) {
            return $next($command);
        }

        $cacheKey = $this->keyGenerator->generateFromCommand($command);
        $tags = $command->getCacheTags();
        $ttl = $command->getCacheTtl() ?? $this->defaultTtl;

        $cacheStore = $tags === [] ? $this->cache : $this->cache->tags($tags);

        return $cacheStore->remember(
            $cacheKey,
            $ttl,
            fn () => $next($command)
        );
    }
}
