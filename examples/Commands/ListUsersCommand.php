<?php

namespace Nactu\Examples\Commands;

use Nactu\Contracts\CacheableQuery;

readonly class ListUsersCommand implements CacheableQuery
{
    private int $cacheTTL;

    public function __construct(
        public ?string $search = null,
        public int $perPage = 20,
        public string $sortBy = 'created_at',
        public string $sortDirection = 'desc',
        private bool $bypassCache = false
    ) {
        $this->cacheTTL = 10;
    }

    public function getCacheTtl(): ?int
    {
        return $this->cacheTTL;
    }

    public function getCacheTags(): array
    {
        $tags = ['users'];

        if ($this->search !== null) {
            $tags[] = 'search';
            $tags[] = 'search:'.md5($this->search);
        }

        return $tags;
    }

    public function shouldBypassCache(): bool
    {
        return $this->bypassCache;
    }
}
