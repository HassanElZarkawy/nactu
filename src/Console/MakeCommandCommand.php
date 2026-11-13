<?php

declare(strict_types=1);

namespace Nactu\Console;

class MakeCommandCommand extends BusGenerator
{
    protected $name = 'nactu:command';

    protected $description = 'Create a new command and its handler';

    protected $type = 'Command';

    protected function classSuffix(): string
    {
        return 'Command';
    }

    protected function namespaceSegment(): string
    {
        return 'Commands';
    }

    protected function handlerType(): string
    {
        return 'Handler';
    }

    protected function subjectVariable(): string
    {
        return 'command';
    }
}
