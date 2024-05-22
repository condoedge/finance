<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;
use Kompo\Table;
use Illuminate\Support\Carbon;
use Kompo\Elements\Element;

class InvoicesTable extends Table
{
    public $containerClass = 'container-fluid';
    public $itemsWrapperClass = 'bg-white rounded-2xl p-4';

    protected $budgetId;
    protected $unitId;
    protected $unionId;

    public $perPage = 50;

    public function created()
    {
        $this->budgetId = $this->parameter('budget_id') ?: request('budget_id');
        $this->unitId = $this->prop('unit_id');
        $this->unionId = currentUnionId();

        Element::macro('gotoInvoice', function($invoiceId){
            return $this->href('invoice.page', [
                'id' => $invoiceId,
            ]);
        });
    }

    public function query()
    {
        $query = Invoice::with('tags', 'customer', 'payments')->where('union_id', $this->unionId);

        if ($this->budgetId) {
            $query = $query->where('budget_id', $this->budgetId);
        } else {
            //Auto-generated contributions invoiced more than one week in the future are hidden by default!
            $query = $query->where(fn($q) => $q->whereNull('budget_id')->orWhere('invoiced_at', '<', Carbon::now()->addDays(28)->format('Y-m-d')));
        }

        if (request('month_year')) {
            $query = $query->whereRaw('LEFT(invoiced_at, 7) = ?', [request('month_year')]);
        }

        if ($unitId = (request('customer') ?: $this->unitId)) {
            $query = $query->where('customer_type', 'unit')->where('customer_id', $unitId);
        }

        return $query->orderByDesc('invoiced_at')->orderByDesc('id');
    }

    public function top()
    {
        return _Rows(
            _FlexBetween(
                _TitleMain('finance.receivables')->class('mb-4'),
                _FlexEnd4(
                    _Dropdown('Actions')->togglerClass('vlBtn')->rIcon('icon-down')
                        ->content(
                            _DropdownLink('finance.new-invoice-contribution')->icon(_Sax('add',20))
                                ->href('invoice.form'),
                            _DropdownLink('finance.new-credit-note')->icon(_Sax('card-add',20))
                                ->href('invoice-credit.form'),
                            /*_DropdownLink('finance.grouped-payments-per-unit')->icon(_Sax('moneys',20))
                                ->selfCreate('getPaymentPrepayInvoiceModal')->inModal(),
                            _DropdownLink('finance.advance-payments-per-unit')->icon(_Sax('money-time',20))
                                ->selfGet('getPaymentAllAccomptesModal')->inModal(),
                            _DropdownLink('finance.send-contributions')->icon(_Sax('sms',20))
                                ->selfCreate('getContributionSendingModal')->inModal(),*/
                        )
                        ->alignRight()
                        ->class('relative z-10')
                )->class('mb-4')
            )->class('flex-wrap'),
            _Columns(
                _Dropdown('finance.selection')->rIcon('icon-down')
                    ->togglerClass('vlBtn bg-info')->class('relative z-10 mb-4')
                    ->submenu(
                        _DropdownLink('finance.record-payment')
                            ->get('payment-entries', ['type' => 'invoice'])->inModal()
                            ->config(['withCheckedItemIds' => true]),
                        _DropdownLink('finance.approve')
                            ->selfPost('approveMany')
                            ->config(['withCheckedItemIds' => true])
                            ->browse(),
                    ),
                _Select()->placeholder('finance.client')
                    ->name('customer', false)->options(/* TODO */)
                    ->default($this->unitId)
                    ->filter(),
                _Select()->placeholder('general.filter-by-month')
                    ->name('month_year', false)
                    ->options(
                        Invoice::where('union_id', $this->unionId)
                            ->selectRaw("DATE_FORMAT(invoiced_at, '%Y-%m') as value, DATE_FORMAT(invoiced_at, '%M %Y') as label")->distinct()
                            ->orderByDesc('value')
                            ->pluck('label', 'value')
                    )
                    ->filter(),

                _Select()->placeholder('general.filter-by-status')
                    ->name('status')->options(Invoice::statuses())
                    ->filter(),
                _Select()->placeholder('finance.select-specific-budget')
                    ->name('budget_id')->options(/* TODO */)
                    ->default($this->budgetId)
                    ->filter(),
            )->alignCenter()
        );
    }

    public function headers()
    {
        return [
            _CheckAllItems()->class('w-1/12'),
            _Th('general.date')->sort('invoiced_at')->class('w-1/6'),
            _Th('Invoice number')->sort('invoice_number')->class('w-1/6'),
            _Th('Type')->class('w-1/6'),
            _Th('general.customer')->sort('customer_id')->class('w-1/4'),
            _Th('general.status')->sort('status')->class('w-1/6'),
            _Th('finance.amount-due')->class('text-right')->class('w-1/12'),
            _Th()->class('w-1/12'),
        ];
    }

    public function render($invoice)
    {
    	return _TableRow(
            _CheckSingleItem($invoice->id),
            _Rows(
                _HtmlDate($invoice->invoiced_at)->class('taxt-gray-400 font-bold'),
                _Flex2(
                    _Html('finance.due'),
                    _HtmlDate($invoice->due_at)
                )->class('text-xs text-gray-600')
            )->gotoInvoice($invoice->id),
            _Rows(
                _Html($invoice->invoice_number)->class('group-hover:underline'),
            )->gotoInvoice($invoice->id),
            _Html($invoice->isReimbursment() ? 'Credit' : 'Invoice'),
            _Html($invoice->customer_label),
            $invoice->statusBadge()
                ->class('text-xs'),
            _Rows(
                _Currency($invoice->due_amount),
                _Flex(
                    _Html('finance.total'),
                    _Currency($invoice->total_amount),
                )->class('space-x-2 text-sm text-gray-600'),
            )->class('items-end'),
            _TripleDotsDropdown(

                $this->dropdownLink('general.view')->gotoInvoice($invoice->id),

                !$invoice->formOpenable(currentUnion()) ? null :
                    $this->dropdownLink('general.edit')->href($invoice->getEditRoute(), ['id' => $invoice->id,]),

                !$invoice->canApprove() ? null : $this->dropdownLink('Approve')->selfPost('approveInvoice', ['id' => $invoice->id])->browse(),

                (!$invoice->canPay() || ($invoice->due_amount <= 0)) ? null :
                    $this->dropdownLink('finance.record-payment')
                        ->get('payment-entry.form', [
                            'type' => 'invoice',
                            'id' => $invoice->id,
                        ])->inModal(),

            )->class('px-2 float-right hover:bg-gray-100 rounded-lg')
            ->alignRight(),
        )->class('group');
    }

    protected function dropdownLink($label)
    {
        return _Link($label)->class('px-4 py-2 border-b border-gray-100 w-32');
    }

    public function approveMany($ids)
    {
        collect($ids)->each(fn($id) => $this->approveInvoice($id));
    }

    public function approveInvoice($id)
    {
        Invoice::findOrFail($id)->markApprovedWithJournalEntries();
    }

    public function getPaymentPrepayInvoiceModal()
    {
        return new PaymentPrepayInvoiceModal();
    }

    public function getPaymentAllAccomptesModal()
    {
        return new PaymentAcompteAddForm();
    }

    public function getContributionSendingModal()
    {
        return new ContributionSendingModal();
    }

    public function js()
    {
        return file_get_contents(resource_path('views/scripts/finance.js'));
    }
}
