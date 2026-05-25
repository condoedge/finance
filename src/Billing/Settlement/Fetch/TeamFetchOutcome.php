<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

/**
 * Per-team outcome of one fetch run. Aggregated by the command for its
 * exit code and surface area for tests.
 */
class TeamFetchOutcome
{
    public function __construct(
        public readonly int $teamId,
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
