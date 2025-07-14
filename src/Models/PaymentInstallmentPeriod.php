<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Billing\Contracts\FinancialPayableInterface;
use Condoedge\Finance\Billing\PayableInterface;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Casts\SafeDecimalCast;
use Illuminate\Support\Collection;
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
 * @property-read Invoice $invoice
 */
class PaymentInstallmentPeriod extends AbstractMainFinanceModel implements FinancialPayableInterface
{
    use \Condoedge\Finance\Billing\Traits\PayableTrait;

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

    // RELATIONS
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    // ACTIONS
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

    // PAYMENT INTERFACE METHODS
    public function getCustomer(): Customer|HistoricalCustomer|null
    {
        return $this->invoice->getCustomer();
    }

    public function onPaymentFailed(array $failureData): void
    {
        $this->invoice->onPaymentFailed($failureData);
    }

    public function onPaymentSuccess(CustomerPayment $payment): void
    {
        $this->invoice->onPaymentSuccess($payment);
    }

    public function getPayableAmount(): SafeDecimal
    {
        return $this->due_amount;
    }

    public function getPayableLines(): Collection
    {
        return collect([
            new \Condoedge\Finance\Models\Dto\Invoices\PayableLineDto([
                'description' => __('finance-with-values-payment-installment-for-invoice', [
                    'invoice_reference' => $this->invoice->invoice_reference,
                    'installment_number' => $this->installment_number,
                ]),
                'sku' => 'installment-period-' . $this->id,
                'price' => $this->due_amount->round(2)->toFloat(),
                'quantity' => 1,
                'amount' => $this->due_amount->round(2)->toFloat(),
            ])
        ]);
    }

    public function getPaymentDescription(): string
    {
        return __('finance-with-values-payment-installment-for-invoice', [
            'invoice_reference' => $this->invoice->invoice_reference,
            'installment_number' => $this->installment_number,
        ]);
    }

    public function getTeamId(): int
    {
        return $this->invoice->getTeamId();
    }

    public function getCustomerName(): ?string
    {
        return $this->invoice->getCustomerName();
    }
}
