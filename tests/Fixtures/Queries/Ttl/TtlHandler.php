<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Queries\Ttl;

class TtlHandler
{
    public static int $constructions = 0;

    public function __construct()
    {
        self::$constructions++;
    }

    public function handle(TtlQuery $query): string
    {
        return 'ttl:'.$query->ttl;
    }
}
