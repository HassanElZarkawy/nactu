<?php

declare(strict_types=1);

namespace Nactu\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base class for bus message generators.
 *
 * Every use case lives in its own folder: the last name segment is the use
 * case, any preceding segments are grouping namespaces. The message class and
 * its handler are generated side by side inside that folder.
 *
 *   nactu:query User/GetUserByCriteria
 *   → app/Queries/User/GetUserByCriteria/GetUserByCriteriaQuery.php
 *   → app/Queries/User/GetUserByCriteria/GetUserByCriteriaHandler.php
 *
 * Generates the message class together with its handler, or — with
 * --handler-only — just the handler.
 */
abstract class BusGenerator extends GeneratorCommand
{
    /**
     * The suffix of the message class (e.g. "Command").
     */
    abstract protected function classSuffix(): string;

    /**
     * The default namespace segment messages are generated into (e.g. "Commands").
     */
    abstract protected function namespaceSegment(): string;

    /**
     * The display name used for handler messages (e.g. "Handler").
     */
    abstract protected function handlerType(): string;

    /**
     * The variable name the handler receives the message as (e.g. "command").
     */
    abstract protected function subjectVariable(): string;

    /**
     * Get the default namespace for the message class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\'.$this->namespaceSegment();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('handler-only')) {
            $this->handleHandlerOnly();

            return null;
        }

        $result = parent::handle();

        if ($result === false) {
            return false;
        }

        $this->createHandler($this->qualifyClass($this->getNameInput()));

        return null;
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->option('cacheable')
            ? __DIR__.'/stubs/cacheable-class.stub'
            : __DIR__.'/stubs/class.stub';
    }

    /**
     * Get the desired class name from the input, expanding the last segment
     * into a use-case folder plus the message class.
     *
     * "User/GetUserByCriteria" becomes "User\GetUserByCriteria\GetUserByCriteriaQuery".
     * Already-qualified input (starting with the root namespace) passes through untouched,
     * only ensuring the message suffix is present.
     */
    protected function getNameInput(): string
    {
        $name = parent::getNameInput();

        if (Str::startsWith($name, $this->rootNamespace())) {
            return Str::endsWith($name, $this->classSuffix())
                ? $name
                : $name.$this->classSuffix();
        }

        $segments = explode('\\', str_replace('/', '\\', trim($name, '\\/')));

        $useCase = (string) array_pop($segments);

        if (Str::endsWith($useCase, $this->classSuffix())) {
            $useCase = Str::replaceLast($this->classSuffix(), '', $useCase);
        }

        $segments[] = $useCase;
        $segments[] = $useCase.$this->classSuffix();

        return implode('\\', $segments);
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array<int, string|int|null>>
     */
    protected function getOptions(): array
    {
        return [
            ['cacheable', 'c', InputOption::VALUE_NONE, 'Create a cacheable '.mb_strtolower($this->type)],
            ['handler-only', null, InputOption::VALUE_NONE, 'Generate only the handler, not the message class'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }

    /**
     * Generate only the handler for the given message name.
     */
    protected function handleHandlerOnly(): void
    {
        if ($this->option('cacheable')) {
            $this->components->warn('The --cacheable option has no effect when using --handler-only.');
        }

        $this->createHandler($this->qualifyClass($this->getNameInput()));
    }

    /**
     * Create the handler for the given qualified message class.
     *
     * The handler is co-located with its message, in the same namespace:
     * App\Queries\User\GetUserByCriteria\GetUserByCriteriaQuery
     * → App\Queries\User\GetUserByCriteria\GetUserByCriteriaHandler
     */
    protected function createHandler(string $subject): void
    {
        $handlerClass = Str::replaceLast($this->classSuffix(), 'Handler', $subject);

        $path = $this->getHandlerPath($handlerClass);

        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->error($this->handlerType().' already exists.');

            return;
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->sortImports($this->buildHandlerClass($handlerClass, $subject)));

        $this->components->info(sprintf('%s [%s] created successfully.', $this->handlerType(), $path));
    }

    /**
     * Get the destination path for the given handler class.
     */
    protected function getHandlerPath(string $handlerClass): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $handlerClass);

        /** @var Application $app */
        $app = $this->laravel;

        return $app->path().'/'.str_replace('\\', '/', $name).'.php';
    }

    /**
     * Build the handler class with the given name.
     *
     * @throws FileNotFoundException
     */
    protected function buildHandlerClass(string $handlerClass, string $subject): string
    {
        $stub = $this->files->get(__DIR__.'/stubs/handler.stub');

        $stub = $this->replaceNamespace($stub, $handlerClass)->replaceClass($stub, $handlerClass);

        return str_replace(
            ['{{ subjectClass }}', '{{ subjectVariable }}'],
            [Str::afterLast($subject, '\\'), $this->subjectVariable()],
            $stub,
        );
    }
}
