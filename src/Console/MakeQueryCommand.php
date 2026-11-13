<?php

declare(strict_types=1);

namespace Nactu\Console;

class MakeQueryCommand extends BusGenerator
{
    protected $name = 'nactu:query';

    protected $description = 'Create a new query and its handler';

    protected $type = 'Query';

    protected function classSuffix(): string
    {
        return 'Query';
    }

    protected function namespaceSegment(): string
    {
        return 'Queries';
    }

    protected function handlerType(): string
    {
        return 'QueryHandler';
    }

    protected function subjectVariable(): string
    {
        return 'query';
    }
}
