<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Utils\Kompo\Common\WhiteTable;

class CustomerInvoicesToBeAppliedTable extends WhiteTable
{
    protected $customerId;
    protected $preselectInvoiceId;

    public $itemsWrapperClass = 'px-6 py-2 TableWithoutRowsBorders';

    public function created()
    {
        $this->customerId = $this->prop('customer_id');
        $this->preselectInvoiceId = $this->prop('preselect_invoice_id');
    }

    public function query()
    {
        return InvoiceModel::canApplyOnIt()
            ->forCustomer($this->customerId);
    }

    public function headers()
    {
        return [
            _Th('finance-invoice-reference'),
            _Th('finance-invoice-date'),
            _Th('finance-invoice-total-amount'),
            _Th('finance-invoice-due-amount'),
            _Th('finance-invoice-apply'),
        ];
    }

    public function render($invoice)
    {
        return _TableRow(
            _Flex(
                _Rows(
                    _Checkbox()->name('apply_to_' . $invoice->id)->shareToParentForm()->class('!mb-0 child-checkbox')
                        ->when($invoice->id == $this->preselectInvoiceId, fn ($e) => $e->default(1))->emit('checkItemId', ['id' => $invoice->id])
                )->attr([
                    'onclick' => 'event.stopPropagation()',
                ]),
                _Html($invoice->invoice_reference),
            )->class('gap-3'),
            _HtmlDate($invoice->invoice_date),
            _FinanceCurrency($invoice->abs_invoice_total_amount),
            _FinanceCurrency($invoice->abs_invoice_due_amount),
            _Input()->name('amount_applied_to_' . $invoice->id)->shareToParentForm()->class('!mb-0'),
        )->class('text-gray-700');
    }
}
