<?php

declare(strict_types=1);

namespace Nactu\Middlewares;

use Exception;
use Illuminate\Support\Sleep;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CommandRetrier
{
    /**
     * @var list<class-string>
     */
    private array $except;

    /**
     * @param  int  $maxRetries  Total number of attempts (the original attempt plus retries)
     * @param  int  $maxBackoff  Maximum seconds to sleep between retries (console only)
     * @param  list<class-string>  $except  Exception classes that should never be retried
     */
    public function __construct(
        private int $maxRetries = 3,
        private int $maxBackoff = 3,
        array $except = [],
        private readonly LoggerInterface $logger = new NullLogger,
    ) {
        $this->maxRetries = max(1, $maxRetries);
        $this->except = $except;
    }

    /**
     * @throws Exception
     */
    public function __invoke(object $command, callable $next): mixed
    {
        // maxRetries is floored to at least one attempt in the constructor,
        // so the loop always terminates by returning or throwing — hence the
        // condition-less form, which has no fall-through.
        for ($attempt = 1; ; $attempt++) {
            try {
                return $next($command);
            } catch (Exception $e) {
                if ($this->shouldNotRetry($e) || $attempt >= $this->maxRetries) {
                    $this->logger->error('Command failed', [
                        'command' => $command::class,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                if (app()->runningInConsole()) {
                    $delay = min(2 ** ($attempt - 1), $this->maxBackoff);
                    Sleep::sleep($delay);
                }

                $this->logger->warning('Command retry', [
                    'command' => $command::class,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Determine if the failure should not be retried
     */
    protected function shouldNotRetry(Exception $e): bool
    {
        foreach ($this->except as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }
}
