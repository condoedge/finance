<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\AccountBalance;
use App\View\Modal;

class AccountBalanceForm extends Modal
{
	public $model = AccountBalance::class;

  	protected $_Title = 'finance.add-opening-balance';

  	protected $accountId;

    public function created()
    {
    	$this->accountId = $this->parameter('account_id');
    }

    public function beforeSave()
    {
    	$this->model->account_id = $this->accountId;
    }

	public function body()
	{
		return [
			_InputNumber('finance.balance')->name('balance'),
			_Date('finance.balance-date')->name('from_date')->default(date('Y-m-d')),
			_SubmitButton('general.save'),
		];
	}

	public function rules()
	{
		return [
			'balance' => 'required|numeric',
			'from_date' => 'required',
		];
	}
}
