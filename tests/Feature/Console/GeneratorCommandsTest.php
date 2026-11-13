<?php

declare(strict_types=1);

namespace Nactu\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use Nactu\Providers\PackageServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GeneratorCommandsTest extends TestCase
{
    private Filesystem $files;

    protected function getPackageProviders($app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
    }

    protected function tearDown(): void
    {
        foreach (['Commands', 'Queries'] as $directory) {
            $this->files->deleteDirectory(app_path($directory));
        }

        parent::tearDown();
    }

    #[Test]
    public function it_generates_a_command_and_its_handler_in_a_use_case_folder(): void
    {
        $this->artisan('nactu:command', ['name' => 'CreateUser'])->assertSuccessful();

        $commandPath = app_path('Commands/CreateUser/CreateUserCommand.php');
        $handlerPath = app_path('Commands/CreateUser/CreateUserHandler.php');

        $this->assertFileExists($commandPath);
        $this->assertStringContainsString('namespace App\Commands\CreateUser;', $this->files->get($commandPath));
        $this->assertStringContainsString('readonly class CreateUserCommand', $this->files->get($commandPath));

        $handler = $this->files->get($handlerPath);

        $this->assertFileExists($handlerPath);
        $this->assertStringContainsString('namespace App\Commands\CreateUser;', $handler);
        $this->assertStringNotContainsString('use App\Commands\CreateUserCommand;', $handler);
        $this->assertStringContainsString('public function handle(CreateUserCommand $command): mixed', $handler);
    }

    #[Test]
    public function it_appends_the_command_suffix_when_missing(): void
    {
        $this->artisan('nactu:command', ['name' => 'ArchiveUser'])->assertSuccessful();

        $this->assertFileExists(app_path('Commands/ArchiveUser/ArchiveUserCommand.php'));
        $this->assertFileExists(app_path('Commands/ArchiveUser/ArchiveUserHandler.php'));
    }

    #[Test]
    public function it_does_not_double_the_command_suffix(): void
    {
        $this->artisan('nactu:command', ['name' => 'ArchiveUserCommand'])->assertSuccessful();

        $this->assertFileExists(app_path('Commands/ArchiveUser/ArchiveUserCommand.php'));
        $this->assertFileDoesNotExist(app_path('Commands/ArchiveUserCommand/ArchiveUserCommandCommand.php'));
    }

    #[Test]
    public function it_groups_command_and_handler_in_a_domain_folder(): void
    {
        $this->artisan('nactu:command', ['name' => 'Admin/CreateUser'])->assertSuccessful();

        $commandPath = app_path('Commands/Admin/CreateUser/CreateUserCommand.php');
        $handlerPath = app_path('Commands/Admin/CreateUser/CreateUserHandler.php');

        $this->assertFileExists($commandPath);
        $this->assertStringContainsString('namespace App\Commands\Admin\CreateUser;', $this->files->get($commandPath));

        $this->assertFileExists($handlerPath);
        $this->assertStringContainsString('namespace App\Commands\Admin\CreateUser;', $this->files->get($handlerPath));
        $this->assertStringContainsString('public function handle(CreateUserCommand $command): mixed', $this->files->get($handlerPath));
    }

    #[Test]
    public function it_accepts_already_qualified_names(): void
    {
        $this->artisan('nactu:command', ['name' => 'App\Commands\Legacy\ExportCommand'])->assertSuccessful();

        $this->assertFileExists(app_path('Commands/Legacy/ExportCommand.php'));
        $this->assertFileExists(app_path('Commands/Legacy/ExportHandler.php'));
    }

    #[Test]
    public function it_generates_a_cacheable_command(): void
    {
        $this->artisan('nactu:command', ['name' => 'FindUser', '--cacheable' => true])->assertSuccessful();

        $content = $this->files->get(app_path('Commands/FindUser/FindUserCommand.php'));

        $this->assertStringContainsString('use Nactu\Contracts\CacheableQuery;', $content);
        $this->assertStringContainsString('implements CacheableQuery', $content);
        $this->assertStringContainsString('public function getCacheTtl(): ?int', $content);
    }

    #[Test]
    public function it_generates_a_query_and_its_handler_in_a_use_case_folder(): void
    {
        $this->artisan('nactu:query', ['name' => 'ListUsers'])->assertSuccessful();

        $queryPath = app_path('Queries/ListUsers/ListUsersQuery.php');
        $handlerPath = app_path('Queries/ListUsers/ListUsersHandler.php');

        $this->assertFileExists($queryPath);
        $this->assertStringContainsString('namespace App\Queries\ListUsers;', $this->files->get($queryPath));
        $this->assertStringContainsString('readonly class ListUsersQuery', $this->files->get($queryPath));

        $handler = $this->files->get($handlerPath);

        $this->assertFileExists($handlerPath);
        $this->assertStringContainsString('namespace App\Queries\ListUsers;', $handler);
        $this->assertStringContainsString('public function handle(ListUsersQuery $query): mixed', $handler);
    }

    #[Test]
    public function it_generates_a_cacheable_query(): void
    {
        $this->artisan('nactu:query', ['name' => 'ListUsers', '--cacheable' => true])->assertSuccessful();

        $content = $this->files->get(app_path('Queries/ListUsers/ListUsersQuery.php'));

        $this->assertStringContainsString('implements CacheableQuery', $content);
        $this->assertStringContainsString('public function shouldBypassCache(): bool', $content);
    }

    #[Test]
    public function it_generates_only_a_handler_with_handler_only(): void
    {
        $this->artisan('nactu:command', ['name' => 'ShipOrder', '--handler-only' => true])->assertSuccessful();

        $handlerPath = app_path('Commands/ShipOrder/ShipOrderHandler.php');

        $this->assertFileExists($handlerPath);
        $this->assertFileDoesNotExist(app_path('Commands/ShipOrder/ShipOrderCommand.php'));

        $content = $this->files->get($handlerPath);

        $this->assertStringContainsString('namespace App\Commands\ShipOrder;', $content);
        $this->assertStringContainsString('public function handle(ShipOrderCommand $command): mixed', $content);
    }

    #[Test]
    public function it_generates_only_a_query_handler_with_handler_only(): void
    {
        $this->artisan('nactu:query', ['name' => 'ListUsers', '--handler-only' => true])->assertSuccessful();

        $this->assertFileExists(app_path('Queries/ListUsers/ListUsersHandler.php'));
        $this->assertFileDoesNotExist(app_path('Queries/ListUsers/ListUsersQuery.php'));
    }

    #[Test]
    public function it_generates_a_handler_only_in_a_domain_folder(): void
    {
        $this->artisan('nactu:command', ['name' => 'Admin/CreateUser', '--handler-only' => true])->assertSuccessful();

        $handlerPath = app_path('Commands/Admin/CreateUser/CreateUserHandler.php');

        $this->assertFileExists($handlerPath);
        $this->assertFileDoesNotExist(app_path('Commands/Admin/CreateUser/CreateUserCommand.php'));

        $this->assertStringContainsString(
            'namespace App\Commands\Admin\CreateUser;',
            $this->files->get($handlerPath),
        );
    }

    #[Test]
    public function it_skips_existing_classes_without_force(): void
    {
        $this->artisan('nactu:command', ['name' => 'CreateUser'])->assertSuccessful();

        $commandPath = app_path('Commands/CreateUser/CreateUserCommand.php');
        $original = $this->files->get($commandPath);

        $this->artisan('nactu:command', ['name' => 'CreateUser'])->assertSuccessful();

        $this->assertSame($original, $this->files->get($commandPath));
    }

    #[Test]
    public function it_overwrites_existing_classes_with_force(): void
    {
        $this->artisan('nactu:command', ['name' => 'CreateUser'])->assertSuccessful();

        $commandPath = app_path('Commands/CreateUser/CreateUserCommand.php');
        $this->files->append($commandPath, "\n// marker\n");

        $this->artisan('nactu:command', ['name' => 'CreateUser', '--force' => true])->assertSuccessful();

        $this->assertStringNotContainsString('// marker', $this->files->get($commandPath));
    }

    #[Test]
    public function it_does_not_overwrite_an_existing_handler_without_force(): void
    {
        $this->artisan('nactu:command', ['name' => 'CreateUser'])->assertSuccessful();

        $handlerPath = app_path('Commands/CreateUser/CreateUserHandler.php');
        $this->files->put($handlerPath, "<?php\n\n// hand-written handler\n");
        $this->files->delete(app_path('Commands/CreateUser/CreateUserCommand.php'));

        $this->artisan('nactu:command', ['name' => 'CreateUser'])->assertSuccessful();

        $this->assertFileExists(app_path('Commands/CreateUser/CreateUserCommand.php'));
        $this->assertStringContainsString('// hand-written handler', $this->files->get($handlerPath));
    }

    #[Test]
    public function it_overwrites_an_existing_handler_with_force(): void
    {
        $this->artisan('nactu:command', ['name' => 'CreateUser'])->assertSuccessful();

        $handlerPath = app_path('Commands/CreateUser/CreateUserHandler.php');
        $this->files->put($handlerPath, "<?php\n\n// hand-written handler\n");
        $this->files->delete(app_path('Commands/CreateUser/CreateUserCommand.php'));

        $this->artisan('nactu:command', ['name' => 'CreateUser', '--force' => true])->assertSuccessful();

        $this->assertStringNotContainsString('// hand-written handler', $this->files->get($handlerPath));
        $this->assertStringContainsString('class CreateUserHandler', $this->files->get($handlerPath));
    }

    #[Test]
    public function it_warns_when_cacheable_is_combined_with_handler_only(): void
    {
        $this->artisan('nactu:command', ['name' => 'CreateUser', '--cacheable' => true, '--handler-only' => true])
            ->assertSuccessful();

        $this->assertFileExists(app_path('Commands/CreateUser/CreateUserHandler.php'));
        $this->assertFileDoesNotExist(app_path('Commands/CreateUser/CreateUserCommand.php'));
        $this->assertStringNotContainsString(
            'CacheableQuery',
            $this->files->get(app_path('Commands/CreateUser/CreateUserHandler.php')),
        );
    }
}
