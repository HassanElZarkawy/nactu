<?php

namespace Nactu\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for managing cache invalidation with tags and patterns
 */
readonly class CacheInvalidationService
{
    public function __construct(
        private CacheRepository $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * Invalidate cache by tags
     *
     * @param  string|string[]  $tags
     */
    public function invalidateByTags(string|array $tags): bool
    {
        $tags = is_array($tags) ? $tags : [$tags];

        try {
            $this->cache->tags($tags)->flush();

            $this->logger->debug('Cache invalidated by tags', [
                'tags' => $tags,
            ]);

            return true;
        } catch (Throwable $e) {
            $this->logger->error('Failed to invalidate cache by tags', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invalidate specific cache keys
     *
     * @param  string|string[]  $keys
     */
    public function invalidateKeys(string|array $keys): bool
    {
        $keys = is_array($keys) ? $keys : [$keys];

        try {
            foreach ($keys as $key) {
                $this->cache->forget($key);
            }

            $this->logger->info('Cache keys invalidated', [
                'keys' => $keys,
            ]);

            return true;
        } catch (Throwable $e) {
            $this->logger->error('Failed to invalidate cache keys', [
                'keys' => $keys,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clear all cache (use with caution)
     */
    public function clearAll(): bool
    {
        try {
            $this->cache->flush();

            $this->logger->warning('All cache cleared');

            return true;
        } catch (Throwable $e) {
            $this->logger->error('Failed to clear all cache', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Warm cache by executing a callable
     */
    public function warm(string $key, callable $callback, int $ttl, array $tags = []): mixed
    {
        try {
            $cacheStore = $tags === [] ? $this->cache : $this->cache->tags($tags);

            return $cacheStore->remember($key, $ttl, $callback);
        } catch (Throwable $e) {
            $this->logger->error('Failed to warm cache', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return $callback();
        }
    }
}
