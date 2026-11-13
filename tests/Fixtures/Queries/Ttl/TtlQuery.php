<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Queries\Ttl;

use Nactu\Contracts\CacheableQuery;

readonly class TtlQuery implements CacheableQuery
{
    public function __construct(
        public int $ttl = 600,
    ) {}

    public function getCacheTtl(): ?int
    {
        return $this->ttl;
    }

    public function getCacheTags(): array
    {
        return [];
    }

    public function shouldBypassCache(): bool
    {
        return false;
    }
}
