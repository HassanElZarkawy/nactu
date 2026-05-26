<?php

declare(strict_types=1);

namespace Nactu\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeHandlerCommand extends GeneratorCommand
{
    protected $name = 'make:handler';

    protected $description = 'Create a new command handler';

    protected $type = 'Handler';

    protected $hidden = true;

    private ?string $commandClass = null;

    public function getStub(): string
    {
        return __DIR__.'/stubs/handler.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Handlers';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $commandClass = $this->commandClass ?? $this->option('command');
        $commandNamespace = $this->laravel->getNamespace().'Commands';

        $stub = str_replace(
            ['{{ commandNamespace }}', '{{ commandClass }}'],
            [$commandNamespace, $commandClass],
            $stub,
        );

        return $stub;
    }

    public function setCommandClass(string $commandClass): static
    {
        $this->commandClass = $commandClass;

        return $this;
    }

    protected function getOptions(): array
    {
        return [
            ['command', null, InputOption::VALUE_REQUIRED, 'The command class this handler handles'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
