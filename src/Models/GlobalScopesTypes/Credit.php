<?php

namespace Condoedge\Finance\Models\GlobalScopesTypes;

use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\RefundCreditDto;
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
     * Creates a credit note and immediately refunds it in full — money going back to
     * the customer in one step. Refunding an existing credit is PaymentService::refundCredit().
     *
     * $dto->payment_method_id is required: the refund has to say how the money left.
     */
    public static function createCreditPayment(CreateInvoiceDto $dto, $paymentDate): self
    {
        $dto->invoice_type_id = ModelsInvoiceTypeEnum::CREDIT->value;

        $credit = InvoiceService::createInvoice($dto);
        $credit->markApproved();
        $credit->refresh();

        PaymentService::refundCredit(new RefundCreditDto([
            'credit_id' => $credit->id,
            'amount' => $credit->abs_invoice_due_amount,
            'payment_date' => $paymentDate,
            'payment_method_id' => $dto->payment_method_id,
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
