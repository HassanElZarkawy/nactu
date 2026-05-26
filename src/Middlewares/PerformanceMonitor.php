<?php

namespace Nactu\Middlewares;

readonly class PerformanceMonitor
{
    public function __construct(
        private float $slowThreshold = 1.0,
    ) {}

    public function __invoke(object $command, callable $next): mixed
    {
        $start = microtime(true);
        $result = $next($command);
        $duration = microtime(true) - $start;

        if ($duration > $this->slowThreshold) {
            logger()->warning('Slow command execution', [
                'command' => $command::class,
                'duration' => round($duration * 1000, 2).'ms',
            ]);
        }

        return $result;
    }
}
