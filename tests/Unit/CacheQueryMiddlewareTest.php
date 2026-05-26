<?php

declare(strict_types=1);

namespace Nactu\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Nactu\Contracts\CacheableQuery;
use Nactu\Middlewares\CacheQueryMiddleware;
use Nactu\Services\CacheKeyGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheQueryMiddlewareTest extends TestCase
{
    private CacheRepository $cache;

    private CacheKeyGenerator $keyGenerator;

    private CacheQueryMiddleware $middleware;

    protected function setUp(): void
    {
        $this->cache = new CacheRepository(new ArrayStore);
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
        $query = new class implements CacheableQuery
        {
            public function getCacheTtl(): ?int
            {
                return 600;
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

        $result = ($this->middleware)($query, fn () => 'result');

        $this->assertSame('result', $result);
    }

    #[Test]
    public function it_falls_back_to_default_ttl(): void
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

        $result = ($this->middleware)($query, fn () => 'result');

        $this->assertSame('result', $result);
    }

    #[Test]
    public function it_returns_cached_result_on_second_call(): void
    {
        $queryA = new class implements CacheableQuery
        {
            public function getCacheTtl(): ?int
            {
                return 300;
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

        $queryB = new class implements CacheableQuery
        {
            public function getCacheTtl(): ?int
            {
                return 300;
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

        ($this->middleware)($queryA, fn () => 'result_a');

        $result = ($this->middleware)($queryB, fn () => 'result_b');

        $this->assertNotSame('result_a', $result);
    }
}
