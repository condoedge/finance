<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Kompo\Plugins\TableIntoFormSetValuesPlugin;
use Condoedge\Utils\Kompo\Common\WhiteTable;
use Condoedge\Utils\Kompo\Plugins\Base\HasComponentPlugins;

class CustomerInvoicesToBeAppliedTable extends WhiteTable
{
    protected $customerId;

    public $itemsWrapperClass = 'px-6 py-2 TableWithoutRowsBorders';

    public function created()
    {
        $this->customerId = $this->prop('customer_id');
    }

    public function query()
    {
        return InvoiceModel::canApplyOnIt()
            ->forCustomer($this->customerId);
    }

    public function headers()
    {
        return [
            _Th('translate.finance.invoice-reference'),
            _Th('translate.finance.invoice-date'),
            _Th('translate.finance.invoice-total-amount'),
            _Th('translate.finance.invoice-due-amount'),
            _Th('translate.finance.invoice-apply'),
        ];
    }

    public function render($invoice)
    {
        return _TableRow(
            _Flex(
                _CheckSingleItem($invoice->id)->name('apply_to_' . $invoice->id)->shareToParentForm(),
                _Html($invoice->invoice_reference),
            )->class('gap-3'),
            _HtmlDate($invoice->invoice_date),
            _FinanceCurrency($invoice->invoice_total_amount),
            _FinanceCurrency($invoice->invoice_due_amount),

            _Input()->name('amount_applied_to_' . $invoice->id)->shareToParentForm()->class('!mb-0'),
        )->class('text-gray-700');
    }
}