<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Queries\TaggedCacheable;

use Nactu\Contracts\CacheableQuery;

readonly class TaggedCacheableQuery implements CacheableQuery
{
    public function __construct(
        public string $status = 'active',
    ) {}

    public function getCacheTtl(): ?int
    {
        return null;
    }

    /**
     * @return string[]
     */
    public function getCacheTags(): array
    {
        return ['users'];
    }

    public function shouldBypassCache(): bool
    {
        return false;
    }
}
