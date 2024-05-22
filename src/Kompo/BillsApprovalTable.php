<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Bill;
use App\Models\Supplier\Project;
use App\Models\Supplier\Supplier;
use App\View\Messaging\ThreadFormPayablesApproval;
use Kompo\Table;
use Kompo\Elements\Element;

class BillsApprovalTable extends BillsTable
{
    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';

    public $class = 'p-4 card-gray-200';

    public function noItemsFound() {
        return __('finance.all-good-no-payables');
    }

    public $id = 'bills-approval-table'; //Not used just to override parent

    public function created()
    {
        parent::created();

        Element::macro('refreshBills', function(){
            return $this->refresh('bills-table');
        });
    }

    public function query()
    {
        return parent::mainQuery()->whereIn('status', auth()->user()->isBoardMember() ?
            [Bill::STATUS_APPROVAL_SENT] :
            [Bill::STATUS_APPROVAL_SENT, Bill::STATUS_RECEIVED]
        );
    }

    public function top()
    {
        return _FlexBetween(
            _Rows(
                _Html('finance.payable-to-approve')->class('font-semibold'),
                _Html('finance.payable-to-approve-sub')->class('text-xs text-gray-600'),
            ),
            _Dropdown('selection')->rIcon('arrow-down')
                ->class('text-sm')
                ->submenu(
                    auth()->user()->isBoardMember() ? null : $this->approvalAllLink('finance.ask-for-approval', 'askForApprovalAllBills')->inDrawer(),
                    $this->approvalAllLink('finance.approve-all', 'acceptAllBills')->refreshBills(), //Show for both type of users...
                    !auth()->user()->isBoardMember() ? null : $this->approvalAllLink('finance.refuse-all', 'refuseAllBills')->refreshBills(),
                )
                ->alignRight(),
        )->class('mb-4');
    }

    public function headers()
    {
        return;
    }

    public function render($bill)
    {
    	return _TableRow(

            _Rows(
                _Columns(

                    _Flex4(
                        $this->billNumberBlock($bill)->class('font-bold whitespace-nowrap'),

                        $this->supplierBlock($bill),
                    )->class('mb-2 md:mb-0')->col('col-md-6 col-lg-4'),

                    _FlexBetween(
                        $this->datesBlock($bill),

                        $this->amountsBlock($bill),
                    )->class('mb-2 md:mb-0 flex-wrap')->col('col-md-6 col-lg-3'),

                    _FlexEnd4(
                        !auth()->user()->isBoardMember() ? null : _Link('<span class="hidden sm:inline text-sm">'.__('file.add-notes').'</span>')->icon('pencil-alt')->class('text-gray-600 hover:underline')->selfGet('getBillNoteForm', ['id' => $bill->id])->inModal(),
                        auth()->user()->isBoardMember() ? null : (
                            $bill->isSentForApproval() ?
                            _Button('finance.approval-sent')->outlined()->icon('icon-check') :
                            $this->approvalButton('finance.ask-for-approval', 'askForApproval', $bill->id)->inDrawer()
                                ->class('bg-level2 bg-opacity-75')
                        ),
                        $this->approvalButton('Accept', 'acceptBill', $bill->id)->refreshBills(),
                        !auth()->user()->isBoardMember() ? null :
                            $this->approvalButton('Refuse', 'refuseBill', $bill->id)->refreshBills()->class('bg-level2 bg-opacity-75'),
                        _Checkbox()->class('mb-0')->emit('checkItemId', ['id' => $bill->id]),
                    )->class('mb-2 md:mb-0 flex-wrap')->col('col-md-12 col-lg-5'),
                )->alignCenter(),
            )->class('px-4')

        );
    }

    public function getBillNoteForm($id)
    {
        return new BillApprovalNoteForm($id);
    }

    protected function approvalButton($label, $method, $billId)
    {
        return _Button($label)->selfPost($method, ['id' => $billId]);
    }

    protected function approvalAllLink($label, $method)
    {
        return _Link($label)->class('px-4 py-2 border-b border-gray-100 whitespace-nowrap')
            ->selfPost($method)
            ->config(['withCheckedItemIds' => true]);
    }

    public function acceptBill($id)
    {
        Bill::findOrFail($id)->markAccepted();
    }

    public function refuseBill($id)
    {
        Bill::findOrFail($id)->markRefused();
    }

    public function askForApproval($id)
    {
        return new ThreadFormPayablesApproval([
            'bill_ids' => $id,
        ]);
    }

    public function acceptAllBills()
    {
        collect($this->getItemIds())->each(fn($id) => $this->acceptBill($id));
    }

    public function refuseAllBills()
    {
        collect($this->getItemIds())->each(fn($id) => $this->refuseBill($id));
    }

    public function askForApprovalAllBills()
    {
        if (!($itemIds = implode(',', $this->getItemIds()))) {
            return;
        }

        return new ThreadFormPayablesApproval([
            'bill_ids' => $itemIds,
        ]);
    }

    protected function getItemIds()
    {
        return request('itemIds') ?: ($this->query()->count() ? $this->query()->pluck('id')->toArray() : []);
    }
}
