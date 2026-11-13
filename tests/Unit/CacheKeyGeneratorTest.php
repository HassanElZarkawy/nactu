<?php

declare(strict_types=1);

namespace Nactu\Tests\Unit;

use Nactu\Services\CacheKeyGenerator;
use Nactu\Tests\Fixtures\Customers\ListUsersQuery as CustomersListUsersQuery;
use Nactu\Tests\Fixtures\Orders\ListUsersQuery as OrdersListUsersQuery;
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
        $command = new PrivateKeyFixture;

        $key = $this->generator->generateFromCommand($command);

        $this->assertNotEmpty($key);
    }

    #[Test]
    public function it_generates_deterministic_keys(): void
    {
        $key1 = $this->generator->generateFromCommand(new OrdersListUsersQuery);
        $key2 = $this->generator->generateFromCommand(new OrdersListUsersQuery);

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
            /** @var array<int, int> */
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

    #[Test]
    public function it_includes_the_namespace_to_prevent_cross_class_collisions(): void
    {
        $ordersQuery = new OrdersListUsersQuery;
        $customersQuery = new CustomersListUsersQuery;

        $ordersKey = $this->generator->generateFromCommand($ordersQuery);
        $customersKey = $this->generator->generateFromCommand($customersQuery);

        // Same short name, same properties — only the namespace differs
        $this->assertNotSame($ordersKey, $customersKey);
        $this->assertStringContainsString('orders', $ordersKey);
        $this->assertStringContainsString('customers', $customersKey);
    }

    #[Test]
    public function it_produces_128_bit_property_hashes(): void
    {
        $command = new class
        {
            public string $name = 'Hassan';
        };

        $key = $this->generator->generateFromCommand($command);
        $hash = substr($key, (int) strrpos($key, ':') + 1);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $hash);
    }

    #[Test]
    public function it_excludes_null_properties_from_the_property_hash(): void
    {
        $withNull = new NullKeyFixture;
        $withoutProperty = new NoPropKeyFixture;

        $hashWithNull = substr(
            $this->generator->generateFromCommand($withNull),
            (int) strrpos($this->generator->generateFromCommand($withNull), ':') + 1,
        );
        $hashWithoutProperty = substr(
            $this->generator->generateFromCommand($withoutProperty),
            (int) strrpos($this->generator->generateFromCommand($withoutProperty), ':') + 1,
        );

        $this->assertSame($hashWithoutProperty, $hashWithNull);
    }

    #[Test]
    public function it_handles_uninitialized_typed_properties(): void
    {
        $command = new UninitializedKeyFixture;

        $key1 = $this->generator->generateFromCommand($command);
        $key2 = $this->generator->generateFromCommand($command);

        $this->assertSame($key1, $key2);
    }

    #[Test]
    public function it_ignores_static_properties(): void
    {
        $withStatic = new StaticKeyFixture;
        $withoutStatic = new PlainKeyFixture;

        $hashWithStatic = substr(
            $this->generator->generateFromCommand($withStatic),
            (int) strrpos($this->generator->generateFromCommand($withStatic), ':') + 1,
        );
        $hashWithoutStatic = substr(
            $this->generator->generateFromCommand($withoutStatic),
            (int) strrpos($this->generator->generateFromCommand($withoutStatic), ':') + 1,
        );

        $this->assertSame($hashWithoutStatic, $hashWithStatic);
    }

    #[Test]
    public function it_hashes_arrays_with_invalid_utf8_deterministically(): void
    {
        // "\xB1\x31" is not valid UTF-8, so json_encode() returns false for it
        $command = new class
        {
            /** @var array<int, string> */
            public array $payload = ["\xB1\x31"];
        };

        $key1 = $this->generator->generateFromCommand($command);
        $key2 = $this->generator->generateFromCommand($command);

        $this->assertSame($key1, $key2);
    }

    #[Test]
    public function it_handles_cyclic_object_references(): void
    {
        $nodeA = new CyclicKeyFixture;
        $nodeB = new CyclicKeyFixture;
        $nodeA->child = $nodeB;
        $nodeB->child = $nodeA;

        $key = $this->generator->generateFromCommand($nodeA);

        $this->assertNotEmpty($key);
    }

    #[Test]
    public function it_keeps_zero_and_falsy_parts(): void
    {
        $this->assertSame('user:0', $this->generator->generate(['user', 0]));
        $this->assertSame('user', $this->generator->generate(['user', '']));
    }

    #[Test]
    public function it_hashes_array_parts(): void
    {
        $key = $this->generator->generate(['users', ['status' => 'active']]);
        $hash = substr($key, (int) strrpos($key, ':') + 1);

        $this->assertStringStartsWith('users:', $key);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $hash);
        $this->assertSame($key, $this->generator->generate(['users', ['status' => 'active']]));
    }

    #[Test]
    public function it_hashes_object_parts(): void
    {
        $key = $this->generator->generate(['lookup', new PlainKeyFixture]);

        $this->assertStringStartsWith('lookup:', $key);
        $this->assertSame($key, $this->generator->generate(['lookup', new PlainKeyFixture]));
    }

    #[Test]
    public function it_ignores_uninitialized_private_properties(): void
    {
        $key1 = $this->generator->generateFromCommand(new PrivateUninitializedKeyFixture);
        $key2 = $this->generator->generateFromCommand(new PrivateUninitializedKeyFixture);

        $this->assertSame($key1, $key2);
        $this->assertNotSame($this->generator->generateFromCommand(new PlainKeyFixture), $key1);
    }
}

final class NullKeyFixture
{
    public string $term = 'hello';

    public ?string $filter = null;
}

final class PrivateKeyFixture
{
    public function __construct(
        private string $name = 'Hassan',
        private int $age = 30,
    ) {}

    public function describe(): string
    {
        return $this->name.' ('.$this->age.')';
    }
}

final class NoPropKeyFixture
{
    public string $term = 'hello';
}

final class UninitializedKeyFixture
{
    public ?string $later;

    public int $age = 30;
}

final class StaticKeyFixture
{
    public static string $kind = 'fixture';

    public int $value = 1;
}

final class PlainKeyFixture
{
    public int $value = 1;
}

final class PrivateUninitializedKeyFixture
{
    public string $name = 'Hassan';

    private string $secret;

    public function hasSecret(): bool
    {
        return isset($this->secret);
    }

    public function withSecret(string $secret): void
    {
        $this->secret = $secret;
    }
}

final class CyclicKeyFixture
{
    public ?CyclicKeyFixture $child = null;
}
