<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Utils\Kompo\Common\Table;

class InvoicePaymentsTable extends Table
{
    protected $invoiceId;
    protected $invoice;

    public function created()
    {
        $this->invoiceId = $this->prop('invoice_id');

        $this->invoice = InvoiceModel::findOrFail($this->invoiceId);
    }

    public function query()
    {
        return $this->invoice->payments()
            ->with('applicable')->getQuery();
    }

    public function headers()
    {
        return [
            _Th('finance-invoice-payment-date'),
            _Th('finance-invoice-payment-method'),
            _Th('finance-invoice-payment-amount'),
        ];
    }

    public function render($invoicePayment)
    {
        $paymentMethod = $invoicePayment->applicable?->paymentMethod;

        return _TableRow(
            _Html($invoicePayment->apply_date?->format('Y-m-d')),
            _Html($paymentMethod?->name ?? 'N/A'),
            _FinanceCurrency($invoicePayment->payment_applied_amount),
        );
    }
}
