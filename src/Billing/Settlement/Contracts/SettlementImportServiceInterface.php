<?php

namespace Condoedge\Finance\Billing\Settlement\Contracts;

use Condoedge\Finance\Billing\Settlement\SettlementImportResult;

interface SettlementImportServiceInterface
{
    /**
     * Import a provider settlement report and record processor fees on payments.
     */
    public function import(string $providerCode, string $filePath): SettlementImportResult;
}
