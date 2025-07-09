<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Payment Service Facade
 *
 * @method static \Condoedge\Finance\Models\CustomerPayment createPayment(\Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto $dto)
 * @method static \Condoedge\Finance\Models\CustomerPayment createPaymentAndApplyToInvoice(\Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto $dto)
 * @method static \Condoedge\Finance\Models\CustomerPayment createPaymentAndApplyToInvoiceInstallmentPeriod(int $installmentPeriodId)
 * @method static \Condoedge\Finance\Models\InvoiceApply applyPaymentToInvoice(\Condoedge\Finance\Models\Dto\Payments\CreateApplyForInvoiceDto $data)
 * @method static \Illuminate\Support\Collection applyPaymentToInvoices(\Condoedge\Finance\Models\Dto\Payments\CreateAppliesForMultipleInvoiceDto $data)
 * @method static \Illuminate\Support\Collection getAvailablePayments(\Condoedge\Finance\Models\Customer|null $customer = null)
 * @method static \Condoedge\Finance\Casts\SafeDecimal calculateAmountLeft(\Condoedge\Finance\Models\CustomerPayment $payment)
 * @method static bool validatePaymentApplication(\Condoedge\Finance\Models\CustomerPayment $payment, \Condoedge\Finance\Models\Invoice $invoice, \Condoedge\Finance\Casts\SafeDecimal $amount)
 * @method static \Illuminate\Support\Collection getPaymentApplications(\Condoedge\Finance\Models\CustomerPayment $payment)
 * @method static bool reversePaymentApplication(\Condoedge\Finance\Models\CustomerPayment $payment, \Condoedge\Finance\Models\Invoice $invoice)
 *
 * @see \Condoedge\Finance\Services\Payment\PaymentServiceInterface
 */
class PaymentService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\Payment\PaymentServiceInterface::class;
    }
}
