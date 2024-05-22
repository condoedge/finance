<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Conciliation;
use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\Transaction;
use App\View\Modal;
use Illuminate\Validation\ValidationException;

abstract class BankSpecialTransactionModal extends Modal
{
	public $model = Transaction::class;

	public $class = 'overflow-y-auto mini-scroll';
	public $style = 'max-height: 95vh';

	protected $conciliationId;
	protected $conciliation;
	protected $bankAccount;
	protected $otherAccount;
	protected $otherAccountOptions;

	public function created()
	{
		$this->conciliationId = $this->prop('conciliation_id');
		$this->conciliation = Conciliation::findOrFail($this->conciliationId);
		$this->bankAccount = $this->conciliation->account;
	}

	public function beforeSave()
	{
		if (request('amount') == 0) {
			throw ValidationException::withMessages([
			   'amount' => [__('finance.amount-should-be-greater-than-zero')],
			]);
		}

		$this->model->union_id = $this->conciliation->account->union_id;
		$this->model->setUserId();
		$this->model->type = $this->transactionType;
	}

	public function afterSave()
	{
		[$a1, $a2, $a3, $a4] = $this->getCreditDebit();

		$bankEntry = $this->model->createEntry(
			request('bank_account'),
			$this->model->transacted_at,
			$a1,
			$a2,
			Entry::METHOD_BANK_PAYMENT,
			$this->model->description,
		);

		$this->model->createEntry(
			request('other_account'),
			$this->model->transacted_at,
			$a3,
			$a4,
			Entry::METHOD_BANK_PAYMENT,
			$this->model->description,
		);

        $this->conciliation->syncEntryToReconciled($bankEntry->id, true);
	}

	public function body()
	{
		return [
			_Date('Date')->name('transacted_at')->default($this->conciliation->end_date),
			_Select('finance.bank-account')->name('bank_account', false)
				->options(
					GlAccount::usableCash()->get()->pluck('display', 'id')
				)->default($this->bankAccount->id),
			_Select('finance.expense-account')->name('other_account', false)
				->options(
					$this->otherAccountOptions
				)->default($this->otherAccount?->id),
			_DollarInput('Amount')->name('amount'),
			_Input('Description')->name('description')->default(__($this->transactionDescription)),
			_FlexEnd(
				_SubmitButton(),
			)
		];
	}

	public function rules()
	{
		return [
			'transacted_at' => 'required',
			'bank_account' => 'required',
			'other_account' => 'required',
			'amount' => 'required|numeric',
		];
	}
}
