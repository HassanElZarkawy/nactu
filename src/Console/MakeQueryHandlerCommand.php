<?php

declare(strict_types=1);

namespace Nactu\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeQueryHandlerCommand extends GeneratorCommand
{
    protected $name = 'make:query-handler';

    protected $description = 'Create a new query handler';

    protected $type = 'QueryHandler';

    protected $hidden = true;

    private ?string $queryClass = null;

    public function getStub(): string
    {
        return __DIR__.'/stubs/query-handler.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\QueryHandlers';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $queryClass = $this->queryClass ?? $this->option('query');
        $queryNamespace = $this->laravel->getNamespace().'Queries';

        $stub = str_replace(
            ['{{ queryNamespace }}', '{{ queryClass }}'],
            [$queryNamespace, $queryClass],
            $stub,
        );

        return $stub;
    }

    public function setQueryClass(string $queryClass): static
    {
        $this->queryClass = $queryClass;

        return $this;
    }

    protected function getOptions(): array
    {
        return [
            ['query', null, InputOption::VALUE_REQUIRED, 'The query class this handler handles'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
