<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

/**
 * Descriptor for one remote file the fetcher saw on the source. Carries just
 * enough metadata for the orchestrator to decide skip/download/import.
 */
class FetchedFile
{
    public function __construct(
        public readonly string $remoteFilename,
        public readonly int $remoteSize,
        public readonly \DateTimeImmutable $remoteModifiedAt,
        public readonly string $remotePath,
    ) {
    }
}
