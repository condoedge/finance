<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;
use Kompo\Table;

class InvoicesForCustomerTable extends Table
{
    protected $customerId;

    public function created()
    {
        $this->customerId = $this->prop('customer_id');
    }

    public function query()
    {
        $query = Invoice::with('tags', 'customer', 'payments')->where('customer_id', $this->customerId);

        return $query->orderByDesc('invoiced_at')->orderByDesc('id');
    }

    public function top()
    {
        return _Rows(

        );
    }

    public function headers()
    {
        return [
            _Th()->class('w-1/12'),
            _Th('finance.invoice.date')->sort('invoiced_at')->class('w-1/6'),
            _Th('finance.invoice.name')->sort('invoice_number')->class('w-1/6'),
            _Th('finance.invoice.status')->sort('status')->class('w-1/6'),
            _Th('finance.invoice.amount-due')->class('text-right')->class('w-1/12'),
            _Th()->class('w-1/12'),
        ];
    }

    public function render($invoice)
    {
    	return _TableRow(
            _Html($invoice->id),
            _Rows(
                _HtmlDate($invoice->invoiced_at)->class('taxt-gray-400 font-bold'),
                _Flex2(
                    _Html('Due'),
                    _HtmlDate($invoice->due_at)
                )->class('text-xs text-gray-600')
            ),
            _Rows(
                _Html($invoice->invoice_number)->class('group-hover:underline'),
            ),
            $invoice->statusBadge()
                ->class('text-xs'),
            _Rows(
                _Currency($invoice->due_amount),
                _Flex(
                    _Html('Total'),
                    _Currency($invoice->total_amount),
                )->class('space-x-2 text-sm text-gray-600'),
            )->class('items-end'),
            _TripleDotsDropdown(


            )->class('px-2 float-right hover:bg-gray-100 rounded-lg')
            ->alignRight(),
        )->class('group');
    }
}
