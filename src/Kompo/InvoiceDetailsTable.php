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
        return _TableRow(
            _Html($invoiceDetail->name),
            _Html($invoiceDetail->revenueAccount?->display)->class('text-right'),
            _Html($invoiceDetail->quantity)->class('text-right'),
            _FinanceCurrency($invoiceDetail->abs_unit_price)->class('text-right'),
            _FinanceCurrency($invoiceDetail->abs_tax_amount)->class('text-right'),
            _FinanceCurrency($invoiceDetail->abs_total_amount) ->class('text-right font-semibold'),
        );
    }
}
