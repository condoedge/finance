<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

/**
 * Outcome of one fetch run. Drives the command's output and exit code.
 */
class FetchOutcome
{
    public function __construct(
        public readonly string $providerCode,
        public readonly int $filesDownloaded = 0,
        public readonly int $filesImported = 0,
        public readonly int $filesSkipped = 0,
        public readonly ?string $error = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->error === null;
    }
}
