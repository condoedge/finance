<?php

namespace Condoedge\Finance\Kompo\PaymentTerms;

use Condoedge\Finance\Kompo\PaymentForm;
use Condoedge\Finance\Models\PaymentInstallmentPeriod;
use Condoedge\Utils\Kompo\Common\Table;

class PaymentInstallmentPeriodsTable extends Table
{
    public $id = 'payment-installment-periods-table';
    protected $invoiceId;

    public function created()
    {
        $this->invoiceId = $this->prop('invoice_id');
    }

    public function query()
    {
        return PaymentInstallmentPeriod::query()
            ->when($this->invoiceId, fn ($q) => $q->where('invoice_id', $this->invoiceId))
            ->orderBy('installment_number');
    }

    public function headers()
    {
        return [
            _Th('translate.installment-number'),
            _Th('translate.due-date'),
            _Th('translate.amount-due-amount'),
            _Th('translate.status'),
            _Th()->class('w-8'),
        ];
    }

    public function render($installmentPeriod)
    {
        return _TableRow(
            _Html($installmentPeriod->installment_number),
            _Html($installmentPeriod->due_date?->format('Y-m-d') ?: '-'),
            _Rows(
                _FinanceCurrency($installmentPeriod->amount),
                _FinanceCurrency($installmentPeriod->due_amount)->class('text-gray-700 text-sm'),
            ),
            $installmentPeriod->status->pill(),
            _TripleDotsDropdown(
                _DropdownLink('translate.pay-period')
                    ->selfPost('getPaymentModal', ['period_id' => $installmentPeriod->id])->inModal(),
            ),
        );
    }

    public function getPaymentModal($installmentPeriodId)
    {
        return new PaymentForm(null, [
            'invoice_id' => $this->invoiceId,
            'period_id' => $installmentPeriodId,
            'refresh_id' => $this->id,
        ]);
    }
}
