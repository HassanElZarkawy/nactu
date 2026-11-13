<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Commands\CreateUser;

readonly class CreateUserCommand
{
    public function __construct(
        public string $name,
    ) {}
}
