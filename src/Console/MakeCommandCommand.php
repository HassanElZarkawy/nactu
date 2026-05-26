<?php

declare(strict_types=1);

namespace Nactu\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeCommandCommand extends GeneratorCommand
{
    protected $name = 'make:command';

    protected $description = 'Create a new command and its handler';

    protected $type = 'Command';

    protected function getStub(): string
    {
        return $this->option('cacheable')
            ? __DIR__.'/stubs/query-command.stub'
            : __DIR__.'/stubs/command.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Commands';
    }

    protected function getNameInput(): string
    {
        $name = parent::getNameInput();

        if (! Str::endsWith($name, 'Command')) {
            $name .= 'Command';
        }

        return $name;
    }

    public function handle(): int
    {
        $commandResult = parent::handle();

        if ($commandResult === self::FAILURE) {
            return self::FAILURE;
        }

        $this->createHandler();

        return self::SUCCESS;
    }

    protected function createHandler(): void
    {
        $name = $this->getNameInput();
        $commandClass = Str::afterLast($name, '\\');
        $handlerClass = Str::replaceLast('Command', 'Handler', $commandClass);

        $handlerName = Str::replaceLast($commandClass, $handlerClass, $name);

        $this->call(MakeHandlerCommand::class, [
            'name' => $handlerName,
            '--command' => $commandClass,
        ]);
    }

    protected function getOptions(): array
    {
        return [
            ['cacheable', 'c', InputOption::VALUE_NONE, 'Create a cacheable query command'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
