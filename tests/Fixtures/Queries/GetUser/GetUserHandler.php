<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Queries\GetUser;

class GetUserHandler
{
    public function handle(GetUserQuery $query): string
    {
        return 'user:'.$query->id;
    }
}
