<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use Condoedge\Finance\Models\AccountBalance;
use App\View\Form;

class AccountBalanceStartDate extends Form
{
	public $model = Union::class;

	protected $oldBalanceDate;

	public function created()
	{
		if (!$this->model->balance_date) {
            $this->model->balance_date = date('Y-m-d');
            $this->model->save();
        }

		$this->oldBalanceDate = $this->model->balance_date;
	}

	public function afterSave()
	{
		AccountBalance::initialBalancesQuery($this->oldBalanceDate, $this->model)
			->update([
				'from_date' => $this->model->balance_date,
			]);
	}

	public function render()
	{
		return [
			_Date('condo.entries-start')->class('noClear')
                ->name('balance_date')->required()
                ->default(date('Y-m-d'))
                ->submit()
				->alert('alert.initial-balance-changed'),
		];
	}

	public function rules()
	{
		return [
			'balance_date' => 'required',
		];
	}
}
