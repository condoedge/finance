<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Condoedge\Finance\Models\PaymentTerm createOrUpdatePaymentTerm(\Condoedge\Finance\Models\Dto\PaymentTerms\CreateOrUpdatePaymentTermDto $dto)
 * @method static void manageNewPaymentTermIntoInvoice(\Condoedge\Finance\Models\Invoice $invoice)
 * @method static array createPaymentInstallmentPeriods(\Condoedge\Finance\Models\Dto\PaymentTerms\CreatePaymentInstallmentPeriodsDto $dto)
 *
 * @see \Condoedge\Finance\Services\PaymentTerm\PaymentTermService
 */
class PaymentTermService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\PaymentTerm\PaymentTermServiceInterface::class;
    }
}
