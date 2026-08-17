<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\InvoiceApply;
use Condoedge\Finance\Models\MorphablesEnum;
use Condoedge\Utils\Kompo\Common\Table;

/**
 * On an invoice: what has been paid against it.
 * On a credit note: where the credit went, plus any money handed back.
 */
class InvoicePaymentsTable extends Table
{
    protected $invoiceId;
    protected $invoice;

    public function created()
    {
        $this->invoiceId = $this->prop('invoice_id');

        $this->invoice = InvoiceModel::findOrFail($this->invoiceId);

        if ($this->invoice->isRefund()) {
            $this->noItemsFound = __('finance-credit-not-used-yet');
        }
    }

    public function query()
    {
        if (!$this->invoice->isRefund()) {
            return $this->invoice->payments()
                ->with('applicable')->getQuery();
        }

        $id = $this->invoiceId;

        // Both legs of fin_invoice_applies: where the credit was spent, and refunds of it.
        return InvoiceApply::where(function ($q) use ($id) {
            $q->where('invoice_id', $id)
                ->orWhere(fn ($a) => $a->where('applicable_type', MorphablesEnum::CREDIT->value)
                    ->where('applicable_id', $id));
        })->with(['invoice', 'applicable'])->orderByDesc('apply_date');
    }

    public function headers()
    {
        return [
            _Th('finance-invoice-payment-date'),
            _Th($this->invoice->isRefund() ? 'finance-applied-to' : 'finance-invoice-payment-method'),
            _Th('finance-invoice-payment-amount'),
        ];
    }

    public function render($invoicePayment)
    {
        return _TableRow(
            _Html($invoicePayment->apply_date?->format('Y-m-d')),
            $this->middleCell($invoicePayment),
            _FinanceCurrency($invoicePayment->payment_applied_amount?->abs()),
        );
    }

    protected function middleCell($invoicePayment)
    {
        $paymentMethod = $invoicePayment->applicable_type == MorphablesEnum::CREDIT->value ? 'finance-credit-note' : $invoicePayment->getPaymentMethod();

        if (!$this->invoice->isRefund()) {
            return _Html(is_string($paymentMethod) ? $paymentMethod : ($paymentMethod?->name ?? 'N/A'));
        }

        // A row keyed on the credit itself is money going back to the customer.
        if ($invoicePayment->invoice_id == $this->invoiceId) {
            return _Rows(
                _Html('finance-refund')->class('font-semibold'),
                _Html($paymentMethod?->name)->class('text-sm text-level1'),
            );
        }

        return _Link($invoicePayment->invoice?->invoice_reference)
            ->href('invoices.show', ['id' => $invoicePayment->invoice_id])
            ->class('underline');
    }
}
