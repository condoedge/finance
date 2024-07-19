<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Bill;
use App\Models\Market\Supplier;
use Kompo\Table;
use Kompo\Elements\Element;

class BillsTable extends Table
{
    public $containerClass = 'container-fluid';
    public $itemsWrapperClass = 'bg-white rounded-2xl p-4';

    public $supplierId;

    public $id = 'bills-table';

    public $perPage = 50;

    public function created()
    {
        $this->supplierId = $this->prop('supplier_id');

        Element::macro('gotoBill', function($billId){
            return $this->href('bill.page', [
                'id' => $billId,
            ]);
        });
    }

    public function query()
    {
        $query = $this->mainQuery()->whereNotIn('status', [Bill::STATUS_RECEIVED, Bill::STATUS_APPROVAL_SENT]);

        if (request('month_year')) {
            $query = $query->whereRaw('LEFT(billed_at, 7) = ?', [request('month_year')]);
        }

        if ($accountIds = request('account_ids')) {
            $query = $query->whereHas('chargeDetails', fn($q) => $q->whereIn('account_id', $accountIds));
        }

        return $query;
    }

    protected function mainQuery()
    {
        return Bill::where('union_id', currentUnion()->id)->with('chargeDetails.glAccount', 'tags')
            ->orderByDesc('billed_at')->orderByDesc('id');
    }

    public function top()
    {
        return _Rows(
            _FlexBetween(
                _TitleMain('finance.payables'),
                _FlexEnd(
                    _Dropdown('finance.actions')->togglerClass('vlBtn')->rIcon('icon-down')
                        ->content(
                            _DropdownLink('finance.add-bill')
                            ->href('bill.form'),
                            _DropdownLink('finance.add-credit-bill')
                                ->href('bills-credit.form'),
                            _DropdownLink('finance.create-recurring-bill')
                                ->href('bills-recurring.form'),
                            _DropdownLink('finance.see-recurring-bills')
                                ->href('bills-recurring.table'),
                        )
                        ->alignRight()
                        ->class('relative z-10')
                )
            )->class('mb-4'),
            currentUnion()->board_approves_bills ? new BillsApprovalTable() : null,
            _Columns(
                _Dropdown('finance.selection')->rIcon('icon-down')
                    ->togglerClass('vlBtn')->class('relative z-10')
                    ->submenu(
                        _Link('finance.record-payment')->class('px-4 py-2 border-b border-gray-100')
                            ->get('payment-entries', ['type' => 'bill'])->inModal()
                            ->config(['withCheckedItemIds' => true]),
                    )
                    ->alignRight(),
                _Select()->placeholder('finance.select-supplier')->class('mb-0 whiteField')
                    ->name('supplier_id')->options(
                        Supplier::whereHas('bills', fn($q) => $q->where('union_id', currentUnion()->id))
                            ->pluck('name_su', 'id')
                    )->default($this->supplierId)
                    ->filter(),
                _MultiSelect()->placeholder('finance.account')->class('mb-0 whiteField')
                    ->name('account_ids', false)->options(GlAccount::usableExpense(currentUnion())->get()->mapWithKeys(fn($account) => $account->getOption()))
                    ->filter(),
                _Select()->placeholder('finance.status')->class('mb-0 whiteField')
                    ->name('status')->options(Bill::statuses())
                    ->filter(),
                _Select()->placeholder('finance.month')->class('mb-0 whiteField')
                    ->name('month_year', false)
                    ->options(
                        Bill::where('union_id', currentUnion()->id)
                            ->selectRaw("DATE_FORMAT(billed_at, '%Y-%m') as value, DATE_FORMAT(billed_at, '%M %Y') as label")->distinct()
                            ->orderBy('value')
                            ->pluck('label', 'value')
                    )
                    ->filter(),
            )->class('mb-4')
            ->alignCenter()
        );
    }

    public function headers()
    {
        return [
            _Div(
                _Flex(
//                    _Html('finance.select-all')->class('font-bold'),
                    _CheckAllItems()->class('!p-0 ml-3')
                ),
            )->class('vlTh w-8 text-left p-3 table-cell'),
            _Th('finance.date')->sort('billed_at')->class('w-40'),
            _Th('finance.supplier')->sort('supplier.name_su')->class('w-60'),
            _Th(__('finance.bill').' #')->sort('bill_number')->class('w-36'),
            _Th('finance.status')->sort('status')->class('w-60'),
            _Th('finance.amount-due')->class('text-right')->class('w-32 text-right'),
            _Th()->class('w-12')
        ];
    }

    public function render($bill)
    {
    	return _TableRow(
            _CheckSingleItem($bill->id)->class('w-8'),

            $this->datesBlock($bill),

            _Rows(
                $this->supplierBlock($bill),
                $bill->getTagPills(),
            ),

            $this->billNumberBlock($bill),

            _Div(
                $bill->statusBadge()->class('text-xs'),
                $bill->approvedBy ? $bill->approvedByLabel()->class('text-xxs text-gray-600') : null,
            ),

            $this->amountsBlock($bill),

            _TripleDotsDropdown(
                !$bill->formOpenable(currentUnion()) ? null :
                    $this->dropdownLink('general.edit')
                        ->href($bill->getEditRoute(), [
                            'id' => $bill->id,
                        ]),
                (!$bill->canPay() || ($bill->due_amount <= 0)) ? null :
                    $this->dropdownLink('finance.record-payment')
                        ->get('payment-entry.form', [
                            'type' => 'bill',
                            'id' => $bill->id,
                        ])->inModal(),
            )->class('px-2 float-right hover:bg-gray-100 rounded-lg')
            ->alignRight(),
        );
    }

    protected function datesBlock($bill)
    {
        return _Rows(
            _HtmlDate($bill->billed_at)->class('taxt-gray-600 font-bold'),
            _Flex2(
                _Html(__('finance.due').':'),
                _HtmlDate($bill->due_at),
            )->class('text-xs text-gray-600')
        )->gotoBill($bill->id);
    }

    protected function supplierBlock($bill)
    {
        return _Link($bill->supplier->display_su)->gotoBill($bill->id);
    }

    protected function billNumberBlock($bill)
    {
        return _Link($bill->bill_number)->gotoBill($bill->id);
    }

    protected function amountsBlock($bill)
    {
        return _Rows(
            _Currency($bill->due_amount),
            _Flex(
                _Html('Total'),
                _Currency($bill->total_amount),
            )->class('space-x-2 text-sm text-gray-600'),
        )->class('items-end')
        ->gotoBill($bill->id);
    }

    protected function dropdownLink($label)
    {
        return _Link($label)->class('px-4 py-2 border-b border-gray-100 w-32');
    }

    public function js()
    {
        return file_get_contents(resource_path('views/scripts/finance.js'));
    }
}
