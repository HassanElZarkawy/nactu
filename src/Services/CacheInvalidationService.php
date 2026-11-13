<?php

declare(strict_types=1);

namespace Nactu\Services;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository as CacheRepository;

/**
 * Provides cache invalidation and warming for cached queries
 */
class CacheInvalidationService
{
    public function __construct(
        private readonly CacheManager $cache,
        private readonly ?string $store = null,
        private readonly int $defaultTtl = 300,
    ) {}

    /**
     * Invalidate all cached entries associated with the given tags.
     * Requires a cache driver that supports tagging
     *
     * @param  string[]  $tags
     */
    public function invalidateByTags(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        $this->store()->tags($tags)->flush();
    }

    /**
     * Invalidate specific cache keys
     */
    public function invalidateKeys(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->store()->forget($key);
        }
    }

    /**
     * Clear the entire underlying cache store
     */
    public function clearAll(): void
    {
        $this->store()->flush();
    }

    /**
     * Pre-populate the cache and return the warmed value
     *
     * @param  Closure(): mixed  $callback
     * @param  string[]  $tags
     */
    public function warm(string $key, Closure $callback, ?int $ttl = null, array $tags = []): mixed
    {
        $cache = $this->store();

        if ($tags !== []) {
            $cache = $cache->tags($tags);
        }

        return $cache->remember($key, $ttl ?? $this->defaultTtl, $callback);
    }

    private function store(): CacheRepository
    {
        /** @var CacheRepository $repository */
        $repository = $this->cache->store($this->store);

        return $repository;
    }
}
