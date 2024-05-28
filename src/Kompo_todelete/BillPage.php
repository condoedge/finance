<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Condo\Unit;
use Condoedge\Finance\Models\Bill;
use App\View\Messaging\ThreadFormPayablesApproval;
use Kompo\Form;

class BillPage extends Form
{
	public $model = Bill::class;

	public $id = 'charge-stage-page'; //shared with bill stage form

	protected $bigClass = 'text-xl font-medium text-level1';
    protected $medClass = 'text-lg font-medium text-level1';

	public function authorizeBoot()
	{
		return true;
		return auth()->user()->can('view', $this->model);
	}

	public function render()
	{
		$i = 1;

		$supplierDisplay = $this->model->supplier->display.($this->model->supplier_number ? ('&nbsp;<span class="text-level1 opacity-60 font-medium">&bull; #'.$this->model->supplier_number.'</span>') : '');

		$relatedParent = ($this->model->relatedProject ?: $this->model->relatedMaintenance)?->parent2;
		$relatedParentRoute = $relatedParent instanceOf \App\Models\Supplier\Project ? 'projects.page' : 'maintenances.page';

		return [
			_FlexBetween(
				_Breadcrumbs(
	                _Link('finance.all-bills')->href('bills.table'),
	                _Html($this->model->bill_number),
	            ),
				_FlexEnd4(
					_Link('finance.create-another-bill')->icon(_Sax('add',20))
						->button()
						->class('ml-4')
						->href('bill.form'),

					!$this->model->formOpenable() ? null :
						_Link('general.edit')->outlined()->icon('icon-edit')
							->href($this->model->getEditRoute(), ['id' => $this->model->id]),
				),
			)->class('flex-wrap mb-12'),
			_FlexBetween(
				_Flex(
					_Rows(
						_TitleMini('Status'),
						$this->model->statusBadge()->class('text-sm !py-1')
                    ),
                    _Rows(
                        _TitleMini('Supplier'),
                        _Link($supplierDisplay)->class($this->bigClass)->href('suppliers.page', ['id' => $this->model->supplier_id]),
                    ),
                )->class('space-x-8'),
				_FlexEnd4(
                    _MiniLabelDate('finance.bill-date', $this->model->billed_at, $this->bigClass),
					_MiniLabelCcy('Total', $this->model->total_amount, $this->bigClass)->class('pl-4'),
				)->class('text-right'),
			)->class('space-x-8 mb-8'),
			$this->stepBox(
				_Rows(
					$this->stepTitle('finance.approval'),
					$this->model->approvedBy ?
						$this->model->approvalEls() : (
							$this->model->isOverApprovalSent() ?
								_Flex4(
                                    _Html()->icon('icon-check'),
                                    _Html('finance.approval-sent')) :
								_Flex4(
									_Html(__('finance.approve-before').':')->class('font-bold'),
									_DateStr($this->model->due_at),
								)
						)
				),
				$this->model->approvedBy ? null :

	                _FlexEnd(
						!currentUnion()->board_approves_bills ? null : (
							$this->model->isOverApprovalSent() ?
								_Button('finance.approval-sent')->outlined()->icon('icon-check') :
								_Button('finance.send-for-approval')->class('bg-info')->selfPost('askForApproval')->inDrawer()
							),
						!$this->model->approvedBy ? _Link('Accept')->outlined()->selfPost('acceptBill')->refresh() : null,
					)->class('space-x-4')
			),
			!$this->model->approvedBy ? null : $this->stepBox(
				$this->model->isReimbursment() ?

					_Rows(
						$this->stepTitle('finance.apply-credit'),
						$this->amountDue()
					) :

					_Rows(
                        $this->stepTitle('finance.pay-supplier'),
						!$this->model->due_amount ?

							$this->model->paidAtLabel()->icon('icon-check') :

							_FlexEnd4(
								$this->amountDue(),
								_MiniLabelDate('finance.due-date', $this->model->due_at, $this->bigClass)->class('border-l border-gray-200 pl-4')
							)
					),
				!$this->model->canPay() ? null : _FlexEnd(

						_Link('finance.record-payment')
							->outlined()
							->get('payment-entry.form', [
								'type' => 'bill',
		                        'id' => $this->model->id,
		                    ])->inModal()
				)
			),
			_TitleMini($this->model->isReimbursment() ? 'finance.credit-note-details' : 'finance.bill-details')->class('uppercase mb-2 mt-4'),
            _Rows(
                _Rows(
                    _FlexBetween(
                        _Flex4(
                            _Rows(
                                _TitleMini('Supplier'),
                                _Link($supplierDisplay)->class($this->medClass)->href('suppliers.page', ['id' => $this->model->supplier_id]),
                            )->class('mr-6'),
                            !$relatedParent ? null : _Rows(
                                _TitleMini('condo.linked-to'),
                                _Html($relatedParent->name)->class('text-lg'),
                            )->href($relatedParentRoute, ['id' => $relatedParent->id]),
                        ),
                        _MiniLabelDate('finance.work-date', $this->model->worked_at, $this->medClass)->class('text-right'),
                    )->class('space-x-8'),
                )->class('card-white px-6 py-4 mx-2 mt-2'),

                new ChargeDetailsTable([
	                'bill_id' => $this->model->id,
	            ])
            )->class('dashboard-card p-4 mb-6'),

			_TitleMini('finance.journal-transactions')->class('uppercase mb-2'),
			(new TransactionsMiniTable([
				'bill_id' => $this->model->id,
			]))->class('dashboard-card p-4 mb-6'),

			!$this->model->notes ? null : _Rows(
				_TitleMini('Note')->class('uppercase mb-2'),
				_Html($this->model->notes)->class('dashboard-card mb-6 p-8'),
			),

			$this->model->attachedFilesBox(),
		];
	}

	protected function stepBox()
	{
		return _FlexBetween(func_get_args())->class('dashboard-card px-8 pt-6 pb-8');
	}

	protected function stepTitle($label)
	{
		return _Html($label)->class($this->bigClass)->class('pb-4 opacity-60');
	}

	public function acceptBill()
    {
        Bill::findOrFail($this->model->id)->markAccepted();
    }

    public function askForApproval()
    {
        return new ThreadFormPayablesApproval([
            'bill_ids' => $this->model->id,
        ]);
    }

	protected function amountDue()
	{
		return _MiniLabelCcy('finance.amount-due', $this->model->due_amount, $this->bigClass);
	}


}
