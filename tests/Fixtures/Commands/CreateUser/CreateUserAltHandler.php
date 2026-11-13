<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Commands\CreateUser;

class CreateUserAltHandler
{
    public function handle(CreateUserCommand $command): string
    {
        return 'manual';
    }
}
