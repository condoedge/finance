<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\AccountBalance;
use Condoedge\Finance\Models\Invoice;
use App\View\Modal;

class AccountsReceivablesInitialAmountsForm extends Modal
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
		$receivables = AccountBalance::where('from_date', currentUnion()->balance_date)->whereHas('account', fn($q) => $q->usableReceivables())->first();

		$this->totalDebit = $receivables->debit_balance;
		$this->totalCredit = $receivables->credit_balance;

		$this->dueDebit = $this->getInvoicesQuery(Invoice::TYPE_PAYMENT)->sum(fn($i) => $i->total_amount);
		$this->dueCredit = $this->getInvoicesQuery(Invoice::TYPE_REIMBURSMENT)->sum(fn($i) => $i->total_amount);

		$this->id('initial-amounts-receivables');
	}

	public function handle()
	{
		if (abs($this->totalDebit - $this->dueDebit) > 0.01) {
			//abort(403, __('error.you-still-havent-distributed-all-debit-notes'));
		}

		if (abs($this->totalCredit - $this->dueCredit) > 0.01) {
			//abort(403, __('error.you-still-havent-distributed-all-credit-notes'));
		}
	}

	public function body()
	{
		return _Rows(
			_FlexAround(
				_TitleMini('finance.remaining-amount-to-distribute')->class('mb-2'),
			),
			_Columns(
				_BoxLabelEl('&nbsp;', _Rows(
					_Html('finance.total:'),
					_Html('finance.distributed:')->class('mt-1'),
					_Html(__('finance.remaining-amount-to-distribute').':')->class('mt-4'),
				),
				)->class('text-sm text-gray-600 p-4'),
				_BoxLabelEl('finance.debit-notes', _Rows(
					_Currency($this->totalDebit),
					_Currency($this->dueDebit),
					_Html($this->totalDebit - $this->dueDebit)->asCurrency()->class('text-2xl leading-9 font-semibold'),
				)
				)->class('text-white bg-level3 p-4'),
				_BoxLabelEl('finance.credit-notes', _Rows(
					_Currency($this->totalCredit),
					_Currency($this->dueCredit),
					_Html($this->totalCredit - $this->dueCredit)->asCurrency()->class('text-2xl leading-9 font-semibold'),
				)
				)->class('bg-level3 text-white p-4'),
			)->class('mb-8'),
			_Columns(
				_Button('finance.new-invoice-contribution')->icon(_Sax('add',20))->outlined()->block()->selfGet('getReceivableAmountForm')->inModal(),
				_Button('finance.new-credit-note')->icon(_Sax('add',20))->outlined()->block()->selfGet('getReceivableCreditForm')->inModal(),
			)->class('mb-4'),
            _Rows(
                _FlexBetween(
                    _Html('Unit')->class('font-bold'),
                    _Html('finance.total-due'),
                )->class('p-2 border-b font-bold'),
                _Rows(
                    $this->getInvoicesQuery()->map(
                        fn($invoice) => _FlexBetween(
                            _Flex4(
                                _DeleteLink()->byKey($invoice)->class('text-gray-600')->refresh('initial-amounts-receivables'),
                                _Html($invoice->customer->display)->class('font-bold'),
                                _Html($invoice->invoice_number),
                            ),
                            _Currency(($invoice->isReimbursment() ? -1 : 1) * $invoice->total_amount),
                        )->class('p-2 border-b border-gray-200')
                    )
                )
			)->class('card-white border border-level3 p-6 mb-4'),
			(abs($this->totalDebit - $this->dueDebit) < 0.01) ? _Html() : _WarningMessage(
				_Html('error.you-still-havent-distributed-all-debit-notes')
			)->class('mb-4'),
			(abs($this->totalCredit - $this->dueCredit) < 0.01) ? _Html() : _WarningMessage(
				_Html('error.you-still-havent-distributed-all-credit-notes')
			)->class('mb-4'),
            _Rows(
                _FlexEnd(
                    _SubmitButton('general.save')
	                	->run('calculateTotalBalances')
                        ->closeSlidingPanel()
            	)->class('mb-8'),
                _Html('finance.if-some-units-still-have-due')
                    ->icon('icon-question-circle')->class('p-4 card-gray-100'),
            )
		)->class('px-6 overflow-y-auto mini-scroll')
		->style('max-height: calc(95vh - 100px)');
	}

	protected function getInvoicesQuery($type = null)
	{
		$query = Invoice::with('customer')->where('union_id', currentUnion()->id)->where('invoiced_at', '<', currentUnion()->balance_date);

		if ($type) {
			$query = $query->where('type', $type);
		}

        return $query->whereHas('transactions',
            fn($q) => $q->whereHas('entries', fn($q) => $q->where('account_id', GlAccount::usableReceivables()->value('id')))
        )->get();
	}

	public function getReceivableAmountForm()
	{
		return new AccountsReceivablesSetAmountForm();
	}

	public function getReceivableCreditForm()
	{
		return new AccountsPaymentsSetAmountForm();
	}
}
