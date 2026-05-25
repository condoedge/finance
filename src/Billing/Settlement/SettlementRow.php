<?php

namespace Condoedge\Finance\Billing\Settlement;

/**
 * One normalized row from a provider settlement report.
 */
class SettlementRow
{
    public function __construct(
        public readonly string $transactionRef,
        public readonly float $fee,
        public readonly ?float $gross = null,
    ) {
    }
}
