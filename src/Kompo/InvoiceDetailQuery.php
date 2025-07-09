<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\InvoiceDetail;
use Condoedge\Utils\Kompo\Common\Query;

class InvoiceDetailQuery extends Query
{
    public $itemsWrapperClass = 'invoice-detail-query-wrapper';

    protected $invoiceId;
    protected $invoice;

    public function created()
    {
        $this->invoiceId = $this->prop('invoice_id');
        $this->invoice = InvoiceModel::findOrFail($this->invoiceId);
    }

    public function query()
    {
        return InvoiceDetail::forInvoice($this->invoiceId);
    }

    public function top()
    {
        return false;
        // return _FlexBetween(
        //     _Html('finance.invoice-details')->class('text-gray-500'),
        //     _Html('finance.amount')->class('text-gray-500')
        // )->class('mb-2');
    }

    public function render($invoiceDetail)
    {
        return _FlexBetween(
            _Rows(
                _Html($invoiceDetail->name),
                _Html($invoiceDetail->description)->class('text-level1 text-sm'),
                _Html(__('finance.quantity-dim', ['qty' => $invoiceDetail->quantity])),
            ),
            _FinanceCurrency($invoiceDetail->total_amount)->class('font-semibold'),
        )->class('!items-end');
    }

    public function bottom()
    {
        return _Columns(
            _Html()->col('col-md-6'),
            // _Rows(
            //     $this->invoice->tax
            // )
        );
    }
}
