<?php

declare(strict_types=1);

namespace Nactu\Tests\Unit;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Log\LogManager;
use Nactu\Middlewares\CommandRetrier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CommandRetrierTest extends TestCase
{
    private Container $app;

    protected function setUp(): void
    {
        $this->app = Container::setInstance(new class extends Container {
            public function runningInConsole(): bool
            {
                return false;
            }
        });

        $logManager = $this->createMock(LogManager::class);
        $this->app->instance('log', $logManager);
        $this->app->alias('log', LogManager::class);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    #[Test]
    public function it_returns_result_on_success(): void
    {
        $retrier = new CommandRetrier(3, 0);
        $command = new class {};

        $result = ($retrier)($command, fn () => 'success');

        $this->assertSame('success', $result);
    }

    #[Test]
    public function it_retries_on_exception_and_succeeds(): void
    {
        $retrier = new CommandRetrier(3, 0);
        $command = new class {};
        $attempt = 0;

        $result = ($retrier)($command, function () use (&$attempt): string {
            $attempt++;

            if ($attempt < 3) {
                throw new Exception('Transient failure');
            }

            return 'recovered';
        });

        $this->assertSame('recovered', $result);
        $this->assertSame(3, $attempt);
    }

    #[Test]
    public function it_throws_after_max_retries_exhausted(): void
    {
        $retrier = new CommandRetrier(3, 0);
        $command = new class {};

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Persistent failure');

        ($retrier)($command, fn () => throw new Exception('Persistent failure'));
    }

    #[Test]
    public function it_does_not_retry_validation_exceptions(): void
    {
        $retrier = new CommandRetrier(3, 0);
        $command = new class {};
        $attempt = 0;

        try {
            ($retrier)($command, function () use (&$attempt): never {
                $attempt++;
                throw new class extends \Illuminate\Validation\ValidationException {
                    public function __construct() {}
                };
            });
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame(1, $attempt);

            return;
        }

        $this->fail('Expected ValidationException was not thrown');
    }

    #[Test]
    public function it_does_not_retry_model_not_found_exceptions(): void
    {
        $retrier = new CommandRetrier(3, 0);
        $command = new class {};
        $attempt = 0;

        try {
            ($retrier)($command, function () use (&$attempt): never {
                $attempt++;
                throw new ModelNotFoundException;
            });
        } catch (ModelNotFoundException) {
            $this->assertSame(1, $attempt);

            return;
        }

        $this->fail('Expected ModelNotFoundException was not thrown');
    }

    #[Test]
    public function it_retries_generic_exceptions(): void
    {
        $retrier = new CommandRetrier(2, 0);
        $command = new class {};
        $attempt = 0;

        try {
            ($retrier)($command, function () use (&$attempt): never {
                $attempt++;
                throw new Exception('Generic error');
            });
        } catch (Exception) {
            $this->assertSame(2, $attempt);

            return;
        }

        $this->fail('Expected Exception was not thrown');
    }

    #[Test]
    public function it_succeeds_on_first_attempt(): void
    {
        $retrier = new CommandRetrier(3, 3);
        $command = new class {};
        $attempt = 0;

        $result = ($retrier)($command, function () use (&$attempt): string {
            $attempt++;

            return 'first_try';
        });

        $this->assertSame('first_try', $result);
        $this->assertSame(1, $attempt);
    }
}
