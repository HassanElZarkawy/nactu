<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Queries\CacheableListUsers;

use Nactu\Contracts\CacheableQuery;

readonly class CacheableListUsersQuery implements CacheableQuery
{
    public function __construct(
        public string $status = 'active',
    ) {}

    public function getCacheTtl(): ?int
    {
        return null;
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
