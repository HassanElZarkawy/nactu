<?php

declare(strict_types=1);

namespace Nactu\Tests\Unit;

use Nactu\Services\CacheKeyGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheKeyGeneratorTest extends TestCase
{
    private CacheKeyGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new CacheKeyGenerator(':');
    }

    #[Test]
    public function it_generates_key_from_public_properties(): void
    {
        $command = new class
        {
            public string $name = 'Hassan';

            public int $age = 30;
        };

        $key = $this->generator->generateFromCommand($command);

        $this->assertStringContainsString(':', $key);
        $this->assertNotEmpty($key);
    }

    #[Test]
    public function it_generates_key_from_private_properties(): void
    {
        $command = new class('Hassan', 30)
        {
            public function __construct(
                private string $name,
                private int $age,
            ) {}
        };

        $key = $this->generator->generateFromCommand($command);

        $this->assertNotEmpty($key);
    }

    #[Test]
    public function it_generates_deterministic_keys(): void
    {
        $command = new class
        {
            public string $name = 'Hassan';

            public int $age = 30;
        };

        $key1 = $this->generator->generateFromCommand($command);
        $key2 = $this->generator->generateFromCommand($command);

        $this->assertSame($key1, $key2);
    }

    #[Test]
    public function it_generates_different_keys_for_different_properties(): void
    {
        $commandA = new class
        {
            public string $name = 'Hassan';
        };

        $commandB = new class
        {
            public string $name = 'Ali';
        };

        $keyA = $this->generator->generateFromCommand($commandA);
        $keyB = $this->generator->generateFromCommand($commandB);

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function it_generates_different_keys_for_different_types(): void
    {
        $commandA = new class
        {
            public string $value = '1';
        };

        $commandB = new class
        {
            public int $value = 1;
        };

        $keyA = $this->generator->generateFromCommand($commandA);
        $keyB = $this->generator->generateFromCommand($commandB);

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function it_ignores_null_properties(): void
    {
        $command = new class
        {
            public ?string $name = null;

            public int $age = 30;
        };

        $keyWithNull = $this->generator->generateFromCommand($command);
        $keyDefault = $this->generator->generateFromCommand($command);

        $this->assertSame($keyWithNull, $keyDefault);
    }

    #[Test]
    public function it_generates_default_key_for_no_properties(): void
    {
        $command = new class {};

        $key = $this->generator->generateFromCommand($command);

        $this->assertStringContainsString('default', $key);
    }

    #[Test]
    public function it_includes_prefix_in_key(): void
    {
        $command = new class {};

        $key = $this->generator->generateFromCommand($command, 'my_prefix');

        $this->assertStringStartsWith('my_prefix:', $key);
    }

    #[Test]
    public function it_generates_key_from_array_parts(): void
    {
        $key = $this->generator->generate(['users', 'list', 'active']);

        $this->assertSame('users:list:active', $key);
    }

    #[Test]
    public function it_generates_for_entity(): void
    {
        $key = $this->generator->forEntity('user', 42);

        $this->assertSame('user:42', $key);
    }

    #[Test]
    public function it_generates_for_entity_with_suffix(): void
    {
        $key = $this->generator->forEntity('user', 42, 'profile');

        $this->assertSame('user:42:profile', $key);
    }

    #[Test]
    public function it_generates_for_list_with_filters(): void
    {
        $key = $this->generator->forList('users', ['status' => 'active']);

        $this->assertStringContainsString('users:list:', $key);
    }

    #[Test]
    public function it_generates_for_list_with_page(): void
    {
        $key = $this->generator->forList('users', [], 3);

        $this->assertMatchesRegularExpression('/page.*3/', $key);
    }

    #[Test]
    public function it_uses_custom_separator(): void
    {
        $generator = new CacheKeyGenerator('|');

        $key = $generator->generate(['users', 'list']);

        $this->assertSame('users|list', $key);
    }

    #[Test]
    public function it_handles_boolean_values(): void
    {
        $commandA = new class
        {
            public bool $active = true;
        };

        $commandB = new class
        {
            public bool $active = false;
        };

        $keyA = $this->generator->generateFromCommand($commandA);
        $keyB = $this->generator->generateFromCommand($commandB);

        $this->assertNotSame($keyA, $keyB);
    }

    #[Test]
    public function it_handles_array_values(): void
    {
        $command = new class
        {
            public array $ids = [1, 2, 3];
        };

        $key = $this->generator->generateFromCommand($command);

        $this->assertNotEmpty($key);
    }

    #[Test]
    public function it_generates_same_hash_for_same_properties(): void
    {
        $command = new class
        {
            public string $name = 'Hassan';

            public int $age = 30;
        };

        $key1 = $this->generator->generateFromCommand($command);
        $key2 = $this->generator->generateFromCommand($command);

        $this->assertSame($key1, $key2);
    }
}
