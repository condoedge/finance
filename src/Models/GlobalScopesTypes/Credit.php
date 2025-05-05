<?php

namespace Condoedge\Finance\Models\GlobalScopesTypes;

use Condoedge\Finance\Models\ApplicableToInvoiceContract;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceTypeEnum as ModelsInvoiceTypeEnum;
use Condoedge\Finance\Models\MorphablesEnum;

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