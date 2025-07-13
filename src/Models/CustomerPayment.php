<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimalCast;
use Illuminate\Support\Facades\DB;

/**
 * Class CustomerPayment
 *
 * @description This model represents payments of customer as separated entities (before being applied to invoices).
 *
 * @package Condoedge\Finance\Models
 *
 * @property int $id
 * @property int $customer_id Foreign key to fin_customers
 * @property \DateTime $payment_date The date of the payment
 * @property \Condoedge\Finance\Casts\SafeDecimal $amount The total amount of the payment
 * @property \Condoedge\Finance\Casts\SafeDecimal $amount_left @CALCULATED BY calculate_payment_amount_left() - Amount left to be applied to invoices
 * @property int $payment_trace_id Foreign key to fin_payment_traces
 *
 **/
class CustomerPayment extends AbstractMainFinanceModel implements ApplicableToInvoiceContract
{
    use \Condoedge\Finance\Models\Traits\ApplicableUtilsTrait;

    protected $table = 'fin_customer_payments';

    protected $casts = [
        'payment_date' => 'date',
        'amount' => SafeDecimalCast::class,
        'amount_left' => SafeDecimalCast::class,
    ];

    // RELATIONS
    public function paymentTrace()
    {
        return $this->belongsTo(PaymentTrace::class);
    }

    // ACTIONS

    // SCOPES
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeHasAmountLeft($query)
    {
        // We can use amount_left column directly. I'm using the function as double check of integrity.
        return $query->whereRaw('calculate_payment_amount_left(fin_customer_payments.id) > 0');
    }

    public static function columnsIntegrityCalculations()
    {
        return [
            'amount_left' => DB::raw('calculate_payment_amount_left(fin_customer_payments.id)'),
            // 'amount' => DB::raw('calculate_payment_amount_with_sign(fin_customer_payments.id)'),
        ];
    }

    // APPLICABLE TO INVOICE CONTRACT
    public static function getApplicableAmountLeftColumn(): string
    {
        return 'amount_left';
    }

    public static function getApplicableNameRawQuery(): string
    {
        return 'CONCAT("PMT #", fin_customer_payments.id)';
    }

    public static function getApplicableTotalAmountColumn(): string
    {
        return 'amount';
    }

    public static function scopeApplicable($builder, $customerId = null)
    {
        return $builder->when($customerId, function ($query) use ($customerId) {
            return $query->where('customer_id', $customerId);
        })->where('amount_left', '>', 0);
    }
}
