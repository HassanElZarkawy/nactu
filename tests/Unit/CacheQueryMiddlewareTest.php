<?php

declare(strict_types=1);

namespace Nactu\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Nactu\Contracts\CacheableQuery;
use Nactu\Middlewares\CacheQueryMiddleware;
use Nactu\Services\CacheKeyGenerator;
use Nactu\Tests\Fixtures\Queries\CacheableListUsers\CacheableListUsersQuery;
use Nactu\Tests\Fixtures\Queries\TaggedCacheable\TaggedCacheableQuery;
use Nactu\Tests\Fixtures\Queries\Ttl\TtlQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheQueryMiddlewareTest extends TestCase
{
    private RecordingArrayStore $store;

    private CacheRepository $cache;

    private CacheKeyGenerator $keyGenerator;

    private CacheQueryMiddleware $middleware;

    protected function setUp(): void
    {
        $this->store = new RecordingArrayStore;
        $this->cache = new CacheRepository($this->store);
        $this->keyGenerator = new CacheKeyGenerator(':');
        $this->middleware = new CacheQueryMiddleware(
            $this->cache,
            $this->keyGenerator,
            300,
        );
    }

    #[Test]
    public function it_bypasses_non_cacheable_queries(): void
    {
        $command = new class {};
        $called = false;

        $result = ($this->middleware)($command, function () use (&$called): string {
            $called = true;

            return 'from_handler';
        });

        $this->assertSame('from_handler', $result);
        $this->assertTrue($called);
    }

    #[Test]
    public function it_caches_cacheable_queries(): void
    {
        $query = new class implements CacheableQuery
        {
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
        };

        $callCount = 0;

        $result1 = ($this->middleware)($query, function () use (&$callCount): string {
            $callCount++;

            return 'cached_result';
        });

        $result2 = ($this->middleware)($query, function () use (&$callCount): string {
            $callCount++;

            return 'should_not_reach';
        });

        $this->assertSame('cached_result', $result1);
        $this->assertSame('cached_result', $result2);
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function it_bypasses_cache_when_should_bypass_is_true(): void
    {
        $query = new class implements CacheableQuery
        {
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
                return true;
            }
        };

        $callCount = 0;

        ($this->middleware)($query, function () use (&$callCount): string {
            $callCount++;

            return 'result_1';
        });

        ($this->middleware)($query, function () use (&$callCount): string {
            $callCount++;

            return 'result_2';
        });

        $this->assertSame(2, $callCount);
    }

    #[Test]
    public function it_uses_custom_ttl_from_query(): void
    {
        $query = new TtlQuery(ttl: 600);
        $cacheKey = $this->keyGenerator->generateFromCommand($query);

        ($this->middleware)($query, fn (): string => 'result');

        $this->assertSame(600, $this->store->secondsByKey[$cacheKey]);
    }

    #[Test]
    public function it_falls_back_to_default_ttl(): void
    {
        $query = new CacheableListUsersQuery;
        $cacheKey = $this->keyGenerator->generateFromCommand($query);

        ($this->middleware)($query, fn (): string => 'result');

        $this->assertSame(300, $this->store->secondsByKey[$cacheKey]);
    }

    #[Test]
    public function it_serves_identical_queries_from_cache(): void
    {
        $callCount = 0;

        ($this->middleware)(new CacheableListUsersQuery, function () use (&$callCount): string {
            $callCount++;

            return 'result_a';
        });

        $result = ($this->middleware)(new CacheableListUsersQuery, function () use (&$callCount): string {
            $callCount++;

            return 'result_b';
        });

        $this->assertSame('result_a', $result);
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function it_caches_tagged_queries(): void
    {
        $callCount = 0;

        $result1 = ($this->middleware)(new TaggedCacheableQuery, function () use (&$callCount): string {
            $callCount++;

            return 'tagged_result';
        });

        $result2 = ($this->middleware)(new TaggedCacheableQuery, function () use (&$callCount): string {
            $callCount++;

            return 'should_not_reach';
        });

        $this->assertSame('tagged_result', $result1);
        $this->assertSame('tagged_result', $result2);
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function it_prefixes_cache_keys(): void
    {
        $middleware = new CacheQueryMiddleware(
            $this->cache,
            $this->keyGenerator,
            300,
            'nactu',
        );

        $query = new CacheableListUsersQuery;
        $expectedKey = $this->keyGenerator->generateFromCommand($query, 'nactu');

        $middleware($query, fn (): string => 'result');

        $this->assertArrayHasKey($expectedKey, $this->store->secondsByKey);
        $this->assertStringStartsWith('nactu:', $expectedKey);
    }
}

final class RecordingArrayStore extends ArrayStore
{
    /** @var array<string, int> */
    public array $secondsByKey = [];

    public function put($key, $value, $seconds)
    {
        $this->secondsByKey[(string) $key] = (int) $seconds;

        return parent::put($key, $value, $seconds);
    }
}
