<?php

namespace Nactu\Middlewares;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;

class CommandRetrier
{
    public function __construct(
        private int $maxRetries = 3,
        private int $maxBackoff = 3,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(object $command, callable $next): mixed
    {
        $attempt = 0;
        $exception = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $next($command);
            } catch (Exception $e) {
                $attempt++;
                $exception = $e;

                if ($this->shouldNotRetry($command, $e) || $attempt >= $this->maxRetries) {
                    logger()->error('Command failed', [
                        'command' => $command::class,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $exception;
                }

                if (app()->runningInConsole()) {
                    $delay = min(2 ** ($attempt - 1), $this->maxBackoff);
                    Sleep::sleep($delay);
                }

                logger()->warning('Command retry', [
                    'command' => $command::class,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        throw $exception;
    }

    /**
     * Determine if command should not be retried
     */
    protected function shouldNotRetry($command, Exception $e): bool
    {
        // Don't retry validation errors
        if ($e instanceof ValidationException) {
            return true;
        }

        // Don't retry not found errors
        if ($e instanceof ModelNotFoundException) {
            return true;
        }

        return false;
    }
}
