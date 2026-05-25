<?php

namespace Condoedge\Finance\Billing\Settlement;

/**
 * Outcome of a settlement-report import.
 */
class SettlementImportResult
{
    public function __construct(
        public readonly string $providerCode,
        public readonly int $rowsParsed,
        public readonly int $matched,
        public readonly int $unmatched,
        public readonly array $unmatchedRefs = [],
    ) {
    }
}
