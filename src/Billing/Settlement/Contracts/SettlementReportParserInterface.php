<?php

namespace Condoedge\Finance\Billing\Settlement\Contracts;

use Illuminate\Support\Collection;

interface SettlementReportParserInterface
{
    /**
     * Parse a provider settlement report into normalized rows.
     *
     * @return Collection<int, \Condoedge\Finance\Billing\Settlement\SettlementRow>
     */
    public function parse(string $filePath, array $config): Collection;
}
