<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\Transaction;
use Illuminate\Validation\ValidationException;
use Kompo\Form;

class TransactionForm extends Form
{
	public $model = Transaction::class;

	public $id = 'transaction-form';

	protected $minDate;

	public function createdDisplay()
	{
		if (!$this->model->id) {
			$this->model->setRelation('entries', collect([new Entry(), new Entry()]));
		}
	}

	public function beforeFill()
	{
		$this->minDate = $this->model->transacted_at ? min($this->model->transacted_at, request('transacted_at')) : request('transacted_at');
	}

	public function beforeSave()
	{
		$this->model->setUnionId();
		$this->model->setUserId();

		$this->model->union->checkIfDateAcceptable($this->minDate);

		$balanceCheck = collect(request('entries'))->map(fn($entry) => $entry['credit'] - $entry['debit'])->sum();

		if ( abs($balanceCheck) > 0.01 ) {
			throw ValidationException::withMessages([
			   'sum' => [__('finance.entries-do-not-balance')],
			]);
		}

		$allAccountIds = collect(request('entries'))->pluck('account_id');

		foreach (['cash', 'payables', 'receivables'] as $scope) {
			if (currentUnion()->accounts()->{$scope}()->whereIn('id', $allAccountIds)->count()) {
				throw ValidationException::withMessages([
				   'sum' => [__('finance.you-cant-enter-or-modifiy-transaction-in-account').' '.$scope.'.'],
				]);
			}
		}


		$this->model->type = Transaction::TYPE_MANUAL_ENTRY;

		$this->model->amount = collect(request('entries'))->map(fn($entry) => $entry['credit'])->sum();
	}

	public function completed()
	{
		$this->model->load('entries');

		//Temporary hack because the child form does not have access to its parent request...
		$this->model->entries->each(function($entry){
			$entry->transacted_at = request('transacted_at');
			$entry->save();
		});
	}

	public function render()
	{
		$dateValue = $this->model->id ? $this->model->transacted_at : date('Y-m-d');

		if ($this->model->isReadonly()) {

			$dateField = _MiniLabelValue('finance.transaction-date', $dateValue)->class('mb-4');
			$descField = _MiniLabelValue('Description', $this->model->description)->class('mb-4');
			$parentLink = $this->model->parentLink();

		}else{

			$dateField = _Date('finance.transaction-date')->name('transacted_at')->default($dateValue);
			$descField = _Textarea('Description')->rows(1)->class('flex-auto');
			$parentLink = null;

		}

		return _Modal(
			_ModalHeader(
				_Breadcrumbs(
	                _Link('finance.transactions')->href('transactions.table', ['account_id' => request('back_account_id')]),
	                _Html('finance.edit-transaction'),
	            ),
				_FlexEnd(
            		$this->model->getVoidLink(true)?->class('text-sm'),
					$this->model->voidPill(),
					$this->model->isReadonly() ? null : _SubmitButton('general.save')->redirect('transactions')
				)->class('space-x-4')
			)->class('mb-4'),
			_ModalBody(
				_TitleMini('finance.transaction-details')->class('mb-2'),
				_Rows(
					_Flex(
						$parentLink,
						$dateField,
						$descField,
					)->alignStart()
					->class('space-x-4'),
				)->class('dashboard-card pt-4 px-4'),
				_TitleMini('finance.entries')->class('mb-2'),
				$this->model->isReadonly() ?
					new TransactionEntriesTable(['transaction_id' => $this->model->id ]) :
					_MultiForm()->noLabel()->name('entries')
						->formClass(TransactionEntryForm::class)
						->asTable([
							'',
							__('finance.description'),
							__('finance.account'),
							__('finance.debit'),
							__('finance.credit'),
						])->addLabel('finance.add-entry', 'icon-plus', 'mt-2 inline-block')
						->class('mb-4')
						->id('transaction-entries'),
				_FlexBetween(
					_ErrorField()->name('sum', false)->class('flex-1 text-lg mr-4'),
					_Rows(
						_FlexBetween(
							_Rows(
								_Html('finance.total-debits')->class('text-xs font-bold text-gray-500'),
								_Currency($this->model->id ? $this->model->debit : 0)
									->class('text-lg font-bold')
									->id('total-debit')
							)->class('px-2'),
							_Html('='),
							_Rows(
								_Html('finance.total-credits')->class('text-xs font-bold text-gray-500'),
								_Currency($this->model->id ? $this->model->credit : 0)
									->class('text-lg font-bold')
									->id('total-credit')
							)->class('px-2'),
						)->class('space-x-4'),
						_FlexAround(
							_Html('finance.difference')->class('text-xs font-bold'),
							_Currency($this->model->id ? $this->model->diff_balance : 0)
								->class('text-lg font-bold')
								->id('total-sum'),
						)->class('space-x-2'),
					)->class('dashboard-card p-2 rounded space-y-4'),
				)
			)
		);
	}

    public function getTransactionVoidModal($id)
    {
        return new TransactionVoidModal($id, [
        	'refresh_id' => 'transaction-form',
        ]);
    }

	public function rules()
	{
		return [
			'transacted_at' => 'required|date',
			'entries.*.account_id' => 'required',
		];
	}

	public function js()
	{
		return <<<javascript

function sumEntries(){

	var debit = 0
	var credit = 0

	$('#transaction-entries tbody tr').each(function(){
		let entryDebit = $(this).find('[name=debit]')[0].value || 0
		let entryCredit = $(this).find('[name=credit]')[0].value || 0

		debit += parseFloat(entryDebit)
		credit += parseFloat(entryCredit)
	})

	setRoundedAmount($('#total-debit'), debit)
	setRoundedAmount($('#total-credit'), credit)
	setRoundedAmount($('#total-sum'), credit - debit)
}

function setRoundedAmount(selector, amount){
	selector[0].innerHTML = amount.toFixed(2)
}

javascript;
	}
}
