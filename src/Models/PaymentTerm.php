<?php

namespace Condoedge\Finance\Models;

use Carbon\Carbon;
use Condoedge\Finance\Facades\PaymentTermService;
use Condoedge\Finance\Models\Dto\PaymentTerms\CreatePaymentInstallmentPeriodsDto;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Kompo\Elements\BaseElement;

/**
 * PaymentTerm Model
 * This model represents a payment term in the finance system.
 * It includes properties for the term type, name, description, and settings.
 *
 * @property int $id Unique identifier for the payment term
 * @property string $term_name Name of the payment term
 * @property string|null $term_description Description of the payment term
 * @property PaymentTermTypeEnum $term_type Type of payment term (e.g., Installment, COD)
 * @property array|null $settings Additional settings for the payment term, such as installment periods or net terms
 */
class PaymentTerm extends AbstractMainFinanceModel
{
    protected $table = 'fin_payment_terms';

    protected $casts = [
        'settings' => 'array',
        'term_type' => PaymentTermTypeEnum::class,
    ];

    public function getDisplayAttribute(): string
    {
        return $this->term_name;
    }

    // SCOPES
    public function scopeCod($query)
    {
        return $query->where('term_type', PaymentTermTypeEnum::COD);
    }

    // ACTIONS
    public function calculateDueDate(string|Carbon $invoiceDate): \DateTime
    {
        $settings = $this->settings ?? [];

        return match ($this->term_type) {
            PaymentTermTypeEnum::COD => carbon($invoiceDate)->toDateTime(),
            PaymentTermTypeEnum::NET => (clone carbon($invoiceDate))->addDays($settings['days']),
            PaymentTermTypeEnum::INSTALLMENT => (clone carbon($invoiceDate))->add($settings['interval_type'], $settings['interval'] * ($settings['periods'] - 1)),
        };
    }

    public function deletable()
    {
        return true;
    }

    public function consideredAsInitialPaid(Invoice $invoice): bool
    {
        return match ($this->term_type) {
            PaymentTermTypeEnum::COD => false,
            PaymentTermTypeEnum::NET => true,
            PaymentTermTypeEnum::INSTALLMENT => $invoice->installmentsPeriods()->orderBy('installment_number')->first()?->status == PaymentInstallPeriodStatusEnum::PAID,
        };
    }

    /**
     * Apply a scope to filter invoices that are considered as initially paid for this term type
     */
    public static function scopeConsideredAsInitialPaid(EloquentBuilder $query, PaymentTermTypeEnum $termType): EloquentBuilder
    {
        return match ($termType) {
            PaymentTermTypeEnum::COD => $query->whereRaw('1 = 0'),
            PaymentTermTypeEnum::NET => $query,
            PaymentTermTypeEnum::INSTALLMENT => $query->whereHas('installmentsPeriods', function ($q) {
                $q->where('installment_number', 1)
                    ->where('status', PaymentInstallPeriodStatusEnum::PAID);
            }),
        };
    }

    public function preview(Invoice $invoice): BaseElement
    {
        $settings = $this->settings ?? [];
        $amount = safeDecimal($invoice->invoice_total_amount);

        return match ($this->term_type) {
            PaymentTermTypeEnum::COD => _Html(__('finance-cod-preview', ['amount' => $amount->toFloat()])),
            PaymentTermTypeEnum::NET => _Html(__('finance-net-preview', ['amount' => $amount->toFloat(), 'due_date' => $this->calculateDueDate($invoice->invoice_date)])),
            PaymentTermTypeEnum::INSTALLMENT => _CardWhite(
                $this->getPreviewInstallments($invoice, $settings)
            )->p4(),
        };
    }

    protected function getPreviewInstallments($invoice, ?array $settings)
    {
        if (!$invoice->installmentsPeriods->isEmpty()) {
            $installments = $invoice->installmentsPeriods->map(fn ($ip) => [
                'installment_number' => $ip->installment_number,
                'amount' => $ip->amount,
                'due_date' => $ip->due_date,
                'status' => $ip->status,
            ]);
        } else {
            $installments = collect(PaymentTermService::createPaymentInstallmentPeriods(
                new CreatePaymentInstallmentPeriodsDto([
                    'periods' => $settings['periods'],
                    'interval' => $settings['interval'],
                    'interval_type' => $settings['interval_type'],
                    'invoice_id' => $invoice->id,
                    'dry_run' => true,
                ])
            ))->map(fn ($i) => (new PaymentInstallmentPeriod())->forceFill($i));
        }

        return $installments->map(function ($period) {
            return _Columns(
                _Html($period['due_date']->format('Y-m-d'))->col('col-md-4'),
                _FinanceCurrency($period['amount'])->col('col-md-4'),
                isset($period['status']) ? $period['status']->pill()->col('col-md-4') : null
            )->class('pb-2');
        });
    }
}
