<?php

namespace Nactu\Examples\Handlers;

use Nactu\Examples\Commands\ListUsersCommand;

class ListUsersHandler
{
    public function handle(ListUsersCommand $command): array
    {
        return [];
    }
}
