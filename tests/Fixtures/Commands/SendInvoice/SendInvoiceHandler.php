<?php

declare(strict_types=1);

namespace Nactu\Tests\Fixtures\Commands\SendInvoice;

use RuntimeException;

class SendInvoiceHandler
{
    private int $attempts = 0;

    public function handle(SendInvoiceCommand $command): string
    {
        $this->attempts++;

        if ($this->attempts < 3) {
            throw new RuntimeException('Transient failure');
        }

        return 'sent';
    }
}
