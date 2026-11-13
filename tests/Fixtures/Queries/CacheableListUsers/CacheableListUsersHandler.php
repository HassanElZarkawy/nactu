<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Queries\CacheableListUsers;

class CacheableListUsersHandler
{
    public static int $executions = 0;

    public function handle(CacheableListUsersQuery $query): string
    {
        static::$executions++;

        return 'users:'.$query->status;
    }
}
