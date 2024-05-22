<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Transaction;

class BankInterestModal extends BankSpecialTransactionModal
{
	protected $_Title = 'finance.add-bank-interest';

	protected $transactionType = Transaction::TYPE_BANKINTEREST;
	protected $transactionDescription = 'finance.bank-account-interest-from-statement';

	public function created()
	{
		parent::created();
		$this->otherAccount = GlAccount::inUnionGl()->bankInterest()->first();
		$this->otherAccountOptions = GlAccount::usableRevenue()->whereRaw('LEFT(code,2) <> ?', ['41'])->get()->pluck('display', 'id');
	}

	public function getCreditDebit()
	{
		$a = request('amount');

		return [
			$a > 0 ? 0 : -$a,
			$a > 0 ? $a : 0,
			$a > 0 ? $a : 0,
			$a > 0 ? 0 : -$a,
		];
	}
}
