<?php

declare(strict_types=1);

namespace Nactu\Middlewares;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

readonly class PerformanceMonitor
{
    public function __construct(
        private float $slowThreshold = 1.0,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function __invoke(object $command, callable $next): mixed
    {
        $start = microtime(true);
        $result = $next($command);
        $duration = microtime(true) - $start;

        if ($duration > $this->slowThreshold) {
            $this->logger->warning('Slow command execution', [
                'command' => $command::class,
                'duration' => round($duration * 1000, 2).'ms',
            ]);
        }

        return $result;
    }
}
