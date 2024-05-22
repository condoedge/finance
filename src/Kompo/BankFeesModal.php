<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Transaction;

class BankFeesModal extends BankSpecialTransactionModal
{
	protected $_Title = 'finance.add-bank-fees';

	protected $transactionType = Transaction::TYPE_BANKFEES;
	protected $transactionDescription = 'finance.bank-account-fees-from-statement';

	public function created()
	{
		parent::created();
		$this->otherAccount = GlAccount::inUnionGl()->bankFees()->first();
		$this->otherAccountOptions = GlAccount::usableExpense()->whereRaw('LEFT(code,2) NOT IN ("52","53","54")')->get()->pluck('display', 'id');
	}

	public function getCreditDebit()
	{
		$a = request('amount');

		return [
			$a > 0 ? $a : 0,
			$a > 0 ? 0 : -$a,
			$a > 0 ? 0 : -$a,
			$a > 0 ? $a : 0,
		];
	}
}
