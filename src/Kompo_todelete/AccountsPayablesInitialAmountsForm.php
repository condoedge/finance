<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountBalance;
use Condoedge\Finance\Models\Bill;
use App\View\Modal;

class AccountsPayablesInitialAmountsForm extends Modal
{
	protected $account;
	protected $totalDebit = 0;
	protected $totalCredit = 0;
	protected $dueDebit = 0;
	protected $dueCredit = 0;

	protected $_Title = 'finance.initial-amounts';
	protected $_Icon = 'cash';

	public $class = 'max-w-4xl overflow-y-auto mini-scroll bg-white';
	public $style = 'height: 100vh';

	public function created()
	{
		$payables = AccountBalance::where('from_date', currentUnion()->balance_date)->whereHas('account', fn($q) => $q->usablePayables())->first();

		$this->totalDebit = $payables->debit_balance;
		$this->totalCredit = $payables->credit_balance;

		$this->dueDebit = $this->getBillsQuery(Bill::TYPE_PAYMENT)->sum(fn($i) => $i->total_amount);
		$this->dueCredit = $this->getBillsQuery(Bill::TYPE_REIMBURSMENT)->sum(fn($i) => $i->total_amount);

		$this->id('initial-amounts-payables');
	}

	public function handle()
	{
		if (abs($this->totalDebit - $this->dueCredit) > 0.01) {
			//abort(403, __('error.you-still-havent-distributed-all-debit-notes'));
		}

		if (abs($this->totalCredit - $this->dueDebit) > 0.01) {
			//abort(403, __('error.you-still-havent-distributed-all-credit-notes'));
		}
	}

	public function body()
	{
		return _Rows(
			_Columns(
				_BoxLabelEl('&nbsp;', _Rows(
						_Html('finance.total:'),
						_Html('finance.distributed:')->class('mt-1'),
						_Html(__('finance.remaining-amount-to-distribute').':')->class('mt-4'),
					),
				)->class('text-sm text-gray-600 p-4'),
				_BoxLabelEl('finance.credit-notes', _Rows(
						_Currency($this->totalDebit),
						_Currency($this->dueCredit),
						_Html($this->totalDebit - $this->dueCredit)->asCurrency()->class('text-2xl leading-9 font-semibold'),
					)
				)->class('bg-level3 text-white p-4'),
				_BoxLabelEl('finance.debit-notes', _Rows(
							_Currency($this->totalCredit),
							_Currency($this->dueDebit),
						_Html($this->totalCredit - $this->dueDebit)->asCurrency()->class('text-2xl leading-9 font-semibold'),
					)
				)->class('text-white bg-info p-4'),
			)->class('mb-8'),
			_Columns(
				_Button('finance.add-bill')->icon(_Sax('add',20))->block()
					->selfGet('getPayableAmountForm')->inModal(),
				_Button('finance.add-credit-bill')->icon(_Sax('add',20))->outlined()->block()
					->selfGet('getPayableAmountForm', ['credit_note' => true])->inModal(),
			)->class('mb-4'),
            _Rows(
                _FlexBetween(
                    _Html('Supplier'),
                    _Html('finance.total-due'),
                )->class('p-2 border-b font-bold'),
                _Rows(
                    $this->getBillsQuery()->map(
                        fn($bill) => _FlexBetween(
                            _Flex4(
                                _DeleteLink()->byKey($bill)->class('text-gray-600')->refresh('initial-amounts-payables'),
                                _Html($bill->supplier->display)->class('font-bold'),
                                _Html($bill->bill_number),
                            ),
                            _Currency(($bill->isReimbursment() ? -1 : 1) * $bill->total_amount),
                        )->class('p-2 border-b border-gray-200')
                    )
                )
            )->class('card-white border border-level3 p-8 mb-4'),
			(abs($this->totalDebit - $this->dueCredit) < 0.01) ? _Html() : _WarningMessage(
				_Html('error.you-still-havent-distributed-all-credit-notes')
			)->class('mb-4'),
			(abs($this->totalCredit - $this->dueDebit) < 0.01) ? _Html() : _WarningMessage(
				_Html('error.you-still-havent-distributed-all-debit-notes')
			)->class('mb-4'),
		_Rows(
            _FlexEnd(
                _SubmitButton('general.save')
	                ->run('calculateTotalBalances')
	                ->closeSlidingPanel()
            )->class('mb-8'),
            _Html('finance.suppliers-unpaid-amounts')
            	->icon('icon-question-circle')->class('p-4 card-gray-100'),
            ),
        )->class('px-6 overflow-y-auto mini-scroll')
		->style('max-height: calc(95vh - 100px)');
	}

	protected function getBillsQuery($type = null)
	{
		$query = Bill::with('supplier')->where('union_id', currentUnion()->id)->where('billed_at', '<', currentUnion()->balance_date);

		if ($type) {
			$query = $query->where('type', $type);
		}

        return $query->whereHas('transactions',
            fn($q) => $q->whereHas('entries', fn($q) => $q->where('account_id', GlAccount::usablePayables()->value('id')))
        )->get();
	}

	public function getPayableAmountForm($creditNote = false)
	{
		if ($creditNote) {
			return new AccountsPayablesCreditSetAmountForm();
		}

		return new AccountsPayablesSetAmountForm();
	}
}
