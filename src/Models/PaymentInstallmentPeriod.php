<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Illuminate\Support\Facades\DB;

/**
 * Represent each installment period of a payment plan for an invoice.
 *
 * This model is used to track the due amounts, payment status, and other details
 * related to each installment period of a payment plan associated with an invoice.
 *
 * @property int $id The unique identifier for the installment period.
 * @property int $invoice_id The ID of the associated invoice.
 * @property SafeDecimal $due_amount The amount due for this installment period.
 * @property SafeDecimal $amount The total amount for this installment period.
 * @property \Illuminate\Support\Carbon $due_date The due date for this installment period.
 * @property int $installment_number The installment number (1-based) for this payment plan.
 * @property PaymentInstallPeriodStatusEnum $status The status of this installment period.
 */
class PaymentInstallmentPeriod extends AbstractMainFinanceModel
{
    protected $table = 'fin_payment_installment_periods';

    protected $fillable = [
        'invoice_id',
        'due_amount',
        'amount',
        'due_date',
        'installment_number',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => SafeDecimalCast::class,
        'due_amount' => SafeDecimalCast::class,
        'invoice_id' => 'integer',
        'status' => PaymentInstallPeriodStatusEnum::class,
    ];

    public static function columnsIntegrityCalculations()
    {
        $statusesIds = PaymentInstallPeriodStatusEnum::PENDING->value . ','
            . PaymentInstallPeriodStatusEnum::PAID->value . ','
            . PaymentInstallPeriodStatusEnum::OVERDUE->value;

        return [
            'due_amount' => DB::raw('calculate_installment_period_due_amount(fin_payment_installment_periods.id)'),
            'status' => DB::raw("calculate_installment_period_status(fin_payment_installment_periods.id, $statusesIds)"),
        ];
    }
}
