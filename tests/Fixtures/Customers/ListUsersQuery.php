<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Customers;

readonly class ListUsersQuery
{
    public function __construct(
        public string $status = 'active',
    ) {}
}
