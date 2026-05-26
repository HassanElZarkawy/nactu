<?php

declare(strict_types=1);

namespace Nactu\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeQueryCommand extends GeneratorCommand
{
    protected $name = 'make:query';

    protected $description = 'Create a new query and its handler';

    protected $type = 'Query';

    protected function getStub(): string
    {
        return $this->option('cacheable')
            ? __DIR__.'/stubs/query-command.stub'
            : __DIR__.'/stubs/query.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Queries';
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput();

        if (! Str::endsWith($name, 'Query')) {
            $name .= 'Query';
        }

        return $name;
    }

    public function handle(): int
    {
        $queryResult = parent::handle();

        if ($queryResult === self::FAILURE) {
            return self::FAILURE;
        }

        $this->createHandler();

        return self::SUCCESS;
    }

    protected function createHandler(): void
    {
        $name = $this->getNameInput();
        $queryClass = Str::afterLast($name, '\\');
        $handlerClass = Str::replaceLast('Query', 'Handler', $queryClass);

        $handlerName = Str::replaceLast($queryClass, $handlerClass, $name);

        $this->call(MakeQueryHandlerCommand::class, [
            'name' => $handlerName,
            '--query' => $queryClass,
        ]);
    }

    protected function getOptions(): array
    {
        return [
            ['cacheable', 'c', InputOption::VALUE_NONE, 'Create a cacheable query'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
