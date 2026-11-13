<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Queries\GetUser;

readonly class GetUserQuery
{
    public function __construct(
        public int $id,
    ) {}
}
