<?php

namespace Condoedge\Finance\Services\PaymentTerm;

use Condoedge\Finance\Models\Dto\PaymentTerms\CreateOrUpdatePaymentTermDto;
use Condoedge\Finance\Models\Dto\PaymentTerms\CreatePaymentInstallmentPeriodsDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentTerm;

interface PaymentTermServiceInterface
{
    public function createOrUpdatePaymentTerm(CreateOrUpdatePaymentTermDto $dto): PaymentTerm;

    public function manageNewPaymentTermIntoInvoice(Invoice $invoice);

    public function createPaymentInstallmentPeriods(CreatePaymentInstallmentPeriodsDto $dto);
}
