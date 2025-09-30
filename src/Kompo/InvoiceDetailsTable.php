<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\InvoiceDetail;
use Kompo\Table;

class InvoiceDetailsTable extends Table
{
    protected $invoiceId;

    public function created()
    {
        $this->invoiceId = $this->prop('invoice_id');
    }

    public function query()
    {
        return InvoiceDetail::where('invoice_id', $this->invoiceId);
    }

    public function headers()
    {
        return [
            _Th('finance-product-service'),
            _Th('finance-revenue-account')->class('text-right'),
            _Th('finance-qty')->class('text-right'),
            _Th('finance-price')->class('text-right'),
            _Th('finance-taxes')->class('text-right'),
            _Th('finance-amount')->class('text-right'),
        ];
    }

    public function render($invoiceDetail)
    {
        $signMultiplier = $invoiceDetail->invoice->invoice_type_id->signMultiplier();

        return _TableRow(
            _Html($invoiceDetail->name),
            _Html($invoiceDetail->revenueAccount?->display)->class('text-right'),
            _Html($invoiceDetail->quantity)->class('text-right'),
            _FinanceCurrency($invoiceDetail->unit_price->multiply($signMultiplier))->class('text-right'),
            _FinanceCurrency($invoiceDetail->tax_amount->multiply($signMultiplier))->class('text-right'),
            _FinanceCurrency($invoiceDetail->total_amount->multiply($signMultiplier))->class('text-right font-semibold'),
        );
    }
}
