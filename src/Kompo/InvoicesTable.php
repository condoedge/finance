<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Utils\Kompo\Common\Table;
use Kompo\Elements\Element;

class InvoicesTable extends Table
{
    public $containerClass = 'container-fluid';
    public $itemsWrapperClass = 'bg-white rounded-2xl p-4'; 

    protected $teamId;

    public $perPage = 50;

    public function created()
    {
        $this->teamId = currentTeamId();

        Element::macro('gotoInvoice', function($invoiceId){
            return $this->href('invoices.show', [
                'id' => $invoiceId,
            ]);
        });
    }

    public function query()
    {
        $query = InvoiceModel::forTeam($this->teamId);

        if (request('month_year')) {
            $query = $query->whereRaw('LEFT(invoice_date, 7) = ?', [request('month_year')]);
        }

        return $query->orderByDesc('invoice_date')->orderByDesc('id');
    }

    public function top()
    {
        return _Rows(
            _FlexBetween(
                _TitleMain('finance-receivables')->class('mb-4'),
                _FlexEnd4(
                    _Dropdown('finance-actions')->togglerClass('vlBtn')->rIcon('icon-down')
                        ->content(
                            _DropdownLink('finance.new-invoice')
                                ->href('invoices.form'),
                        )
                        ->alignRight()
                        ->class('relative z-10')
                )->class('mb-4')
            )->class('flex-wrap'),
            _FlexBetween(
                _Dropdown('finance-grouped-action')->rIcon('icon-down')
                    ->togglerClass('vlBtn')->class('relative z-10 mb-4')
                    ->submenu(
                        _DropdownLink('finance-record-payment')
                            ->get('payment-entries', ['type' => 'invoice'])->inModal()
                            ->config(['withCheckedItemIds' => true]),
                        _DropdownLink('finance-approve')
                            ->selfPost('approveMany')
                            ->config(['withCheckedItemIds' => true])
                            ->browse(),
                    ),

                _Flex(
                    _Columns(
                        // _Select()->placeholder('finance-client')->name('person_id')
                        //     ->options(PersonModel::getOptionsForTeamWithFullName($this->teamId))
                        //     ->filter(),
                        _Select()->placeholder('finance-filter-by-month')
                            ->name('month_year', false)
                            ->options(
                                InvoiceModel::forTeam($this->teamId)
                                    ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') as value, DATE_FORMAT(invoice_date, '%M %Y') as label")->distinct()
                                    ->orderByDesc('value')
                                    ->pluck('label', 'value')
                            )
                            ->filter(),
    
                        // _MultiSelect()->placeholder('finance-filter-by-status')
                        //     ->name('status')->options(InvoiceModel::statuses())
                        //     ->filter(),
                    ),
                    
                    _ExcelExportButton(),
                )->class('gap-4'),
            )->alignCenter()
        );
    }

    public function headers()
    {
        return [
            _CheckAllItems()->class('w-1/12'),
            _Th('finance-date')->sort('invoice_date')->class('w-1/6'),
            _Th('finance-invoice-number')->sort('invoice_number')->class('w-1/6'),
            _Th('finance-type')->class('w-1/12'),
            _Th('finance-client')->sort('customer_id')->class('w-1/4'),
            _Th('finance-status')->sort('status')->class('w-1/6'),
            _Th('finance-amount-due')->class('text-right')->class('w-1/6'),
            _Th()->class('w-1/12'),
        ];
    }

    public function render($invoice)
    {
    	return _TableRow(
            _CheckSingleItem($invoice->id),
            _Rows(
                _HtmlDate($invoice->invoice_date)->class('taxt-gray-400 font-bold'),
                _Flex2(
                    _Html('finance-due-at'),
                    _HtmlDate($invoice->due_at)
                )->class('text-xs text-gray-600')
            )->gotoInvoice($invoice->id),
            _Rows(
                _Html($invoice->invoice_reference)->class('group-hover:underline'),
            )->gotoInvoice($invoice->id),
            _Html($invoice->payment_type_label),
            _Html($invoice->customer_label),
            $invoice->invoice_status_id->pill(),
            _Rows(
                _FinanceCurrency($invoice->invoice_due_amount),
                _Flex(
                    _Html('finance-total'),
                    _FinanceCurrency($invoice->invoice_amount),
                )->class('space-x-2 text-sm text-gray-600'),
            )->class('items-end'),
            // _TripleDotsDropdown(

            //     $this->dropdownLink('finance-view')->gotoInvoice($invoice->id),

            //     $this->dropdownLink('finance-edit')->href($invoice->getEditRoute(), ['id' => $invoice->id,]),

            //     !$invoice->canApprove() ? null : $this->dropdownLink('finance-approve')->selfPost('approveInvoice', ['id' => $invoice->id])->browse(),

            //     (!$invoice->canPay() || ($invoice->due_amount <= 0)) ? null :
            //         $this->dropdownLink('finance-record-payment')
            //             ->get('payment-entry.form', [
            //                 'type' => 'invoice',
            //                 'id' => $invoice->id,
            //             ])->inModal(),

            // )->class('px-2 float-right hover:bg-gray-100 rounded-lg exclude-export')
            // ->alignRight(),
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
        InvoiceModel::findOrFail($id)->markApprovedWithJournalEntries();
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
}
