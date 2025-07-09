<?php

namespace Condoedge\Finance\Models;

use Carbon\Carbon;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Facades\PaymentTermService;
use Condoedge\Finance\Models\Dto\PaymentTerms\CreatePaymentInstallmentPeriodsDto;
use Kompo\Elements\BaseElement;

enum PaymentTermTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case COD = 1; // Cash on Delivery
    case NET = 2; // Net terms (e.g., Net 30, Net 60)
    case INSTALLMENT = 3; // Installment payments

    public function label(): string
    {
        return match ($this) {
            self::COD => __('translate.cod'),
            self::NET => __('translate.net'),
            self::INSTALLMENT => __('translate.installment'),
        };
    }

    public function settingsRules()
    {
        return match ($this) {
            self::COD => [],
            self::NET => [
                'days' => 'required|integer|min:1',
            ],
            self::INSTALLMENT => [
                'periods' => 'required|integer|min:1',
                'interval' => 'required|integer|min:1',
                'interval_type' => 'required|in:days,months,years',
            ],
        };
    }

    public function settingsFields($setting = []): array
    {
        return match ($this) {
            self::COD => [],
            self::NET => [
                _InputNumber('translate.days')->required()->name('settings_days', false)->default($setting['days'] ?? null),
            ],
            self::INSTALLMENT => [
                'periods' => _InputNumber('translate.periods')->required()->name('settings_periods', false)->default($setting['periods'] ?? null),
                'interval_type' => _ButtonGroup('translate.interval_type')
                    ->name('settings_interval_type', false)
                    ->default($setting['interval_type'] ?? 'months')
                    ->options([
                        'days' => __('translate.days'),
                        'months' => __('translate.months'),
                        // 'years' => __('translate.years'),
                    ]),
                'interval' => _InputNumber('translate.interval')->name('settings_interval', false)->default($setting['interval'] ?? null),
            ],
        };
    }

    public function calculateDueDate(Carbon $invoiceDate, ?array $settings): \DateTime
    {
        return match ($this) {
            self::COD => $invoiceDate, // Due immediately
            self::NET => (clone $invoiceDate)->addDays($settings['days']),
            self::INSTALLMENT => (clone $invoiceDate)->add($settings['interval_type'], $settings['interval'] * ($settings['periods'] - 1)),
        };
    }

    public function manageNewPaymentTermIntoInvoice(Invoice $invoice, ?array $settings): void
    {
        switch ($this) {
            case self::COD:
                // No additional management needed for COD
                break;
            case self::NET:
                // No additional management needed for COD
                break;
            case self::INSTALLMENT:
                PaymentTermService::createPaymentInstallmentPeriods(
                    new CreatePaymentInstallmentPeriodsDto([
                        'periods' => $settings['periods'],
                        'interval' => $settings['interval'],
                        'interval_type' => $settings['interval_type'],
                        'invoice_id' => $invoice->id,
                    ])
                );
                break;
        }
    }

    public function manageOldPaymentTermIntoInvoice(Invoice $invoice): void
    {
        // This method can be used to clean up or reset any old payment term data if needed
        switch ($this) {
            case self::COD:
                // No additional management needed for COD
                break;
            case self::NET:
                // No additional management needed for COD
                break;
            case self::INSTALLMENT:
                $invoice->installmentsPeriods()->delete();
                break;
        }
    }

    public function preview(Invoice $invoice, ?array $settings): BaseElement
    {
        $amount = safeDecimal($invoice->invoice_total_amount);

        return match ($this) {
            self::COD => _Html(__('translate.cod-preview', ['amount' => $amount->toFloat()])),
            self::NET => _Html(__('translate.net-preview', ['amount' => $amount->toFloat(), 'due_date' => $this->calculateDueDate($invoice->invoice_date, $settings)])),
            self::INSTALLMENT => _CardWhite(
                $this->getPreviewInstallments($invoice, $settings)
            )->p4(),
        };
    }

    protected function getPreviewInstallments($invoice, ?array $settings)
    {
        if (!$invoice->installmentsPeriods->isEmpty()) {
            $installments = $invoice->installmentsPeriods->map(fn($ip) => [
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
                    'dry_run' => true, // Use dry_run to avoid actual DB insertion
                ])
            ));
        }

        return $installments->map(function ($period) {
            return _Columns(
                _Html($period['installment_number'])->col('col-md-2'),
                _FinanceCurrency($period['amount'])->col('col-md-3'),
                _Html($period['due_date']->format('Y-m-d'))->col('col-md-3'),
                isset($period['status']) ?  $period['status']->pill() : null
            )->class('pb-2 border-bottom border-gray-200');
        });
    }
}