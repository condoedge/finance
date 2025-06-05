<?php

namespace Condoedge\Finance\Models\GlobalScopesTypes;

use Condoedge\Finance\Facades\CustomerPaymentModel;
use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceTypeEnum as ModelsInvoiceTypeEnum;

class Credit extends Invoice implements ApplicableToInvoiceContract
{
    use \Condoedge\Finance\Models\Traits\ApplicableUtilsTrait;

    protected $table = 'fin_invoices';

    protected static function booted()
    {
        static::addGlobalScope('credit', function ($builder) {
            $builder->where('invoice_type_id', ModelsInvoiceTypeEnum::CREDIT);
        });
    }

    // SERVICE
    /**
     * Creates a credit note and apply a payment to it. It's used to pay to the customer.
     */
    public static function createCreditPayment(CreateInvoiceDto $dto, $paymentDate): self
    {
        $dto->invoice_type_id = ModelsInvoiceTypeEnum::CREDIT->value;

        $credit = static::createInvoiceFromDto($dto);

        CustomerPaymentModel::createForCustomerAndApply(new CreateCustomerPaymentForInvoiceDto([
            'customer_id' => $dto->customer_id,
            'invoice_id' => $credit->id,
            'amount' => $credit->invoice_due_amount,
            'payment_date' => $paymentDate,
        ]));

        return $credit;
    }

    // APPLICABLE LOGIC
    public static function getApplicableAmountLeftColumn(): string
    {
        return 'ABS(invoice_due_amount)';
    }

    public static function getApplicableNameRawQuery(): string
    {
        return 'invoice_reference';
    }

    public static function getApplicableTotalAmountColumn(): string
    {
        return 'ABS(invoice_total_amount)';
    }

    public static function scopeApplicable($builder, $customerId = null)
    {
        return $builder->when($customerId, function ($query) use ($customerId) {
            return $query->where('customer_id', $customerId);
        })->pending()->whereRaw(static::getApplicableAmountLeftColumn() . ' > 0');
    }
}