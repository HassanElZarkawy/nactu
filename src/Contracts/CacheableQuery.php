<?php

declare(strict_types=1);

namespace Nactu\Contracts;

/**
 * Marker interface for queries that support caching
 */
interface CacheableQuery
{
    /**
     * Get the cache TTL in seconds
     * Return null to use default TTL from config
     */
    public function getCacheTtl(): ?int;

    /**
     * Get cache tags for this query
     * Tags enable group invalidation
     *
     * @return string[]
     */
    public function getCacheTags(): array;

    /**
     * Whether this specific query instance should bypass cache
     */
    public function shouldBypassCache(): bool;
}
