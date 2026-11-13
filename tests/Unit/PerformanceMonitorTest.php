<?php

declare(strict_types=1);

namespace Nactu\Tests\Unit;

use Illuminate\Log\LogManager;
use Nactu\Middlewares\PerformanceMonitor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PerformanceMonitorTest extends TestCase
{
    #[Test]
    public function it_returns_result_from_handler(): void
    {
        $monitor = new PerformanceMonitor(1.0);
        $command = new class {};

        $result = ($monitor)($command, fn () => 'result');

        $this->assertSame('result', $result);
    }

    #[Test]
    public function it_passes_command_through(): void
    {
        $monitor = new PerformanceMonitor(1.0);
        $command = new class {};
        $received = null;

        ($monitor)($command, function (object $cmd) use (&$received): void {
            $received = $cmd;
        });

        $this->assertSame($command, $received);
    }

    #[Test]
    public function it_does_not_log_fast_commands(): void
    {
        $logger = $this->createMock(LogManager::class);
        $logger->expects($this->never())->method('warning');

        $monitor = new PerformanceMonitor(1.0, $logger);
        $command = new class {};

        $result = ($monitor)($command, fn () => 'fast');

        $this->assertSame('fast', $result);
    }

    #[Test]
    public function it_logs_slow_commands(): void
    {
        $logger = $this->createMock(LogManager::class);
        $logger->expects($this->once())->method('warning');

        $monitor = new PerformanceMonitor(-1.0, $logger);
        $command = new class {};

        $result = ($monitor)($command, fn () => 'slow');

        $this->assertSame('slow', $result);
    }

    #[Test]
    public function it_measures_execution_time(): void
    {
        $monitor = new PerformanceMonitor(1.0);
        $command = new class {};
        $handlerCalled = false;

        ($monitor)($command, function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });

        $this->assertTrue($handlerCalled);
    }
}
