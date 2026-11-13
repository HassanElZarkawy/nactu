<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Commands\CreateUser;

class CreateUserHandler
{
    public function handle(CreateUserCommand $command): string
    {
        return 'created:'.$command->name;
    }
}
