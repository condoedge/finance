<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use Condoedge\Finance\Models\GlAccount;
use App\View\Traits\IsDashboardCard;
use Kompo\Form;

class UnionAnnualInterestForm extends Form
{
    use IsDashboardCard;

	public $model = Union::class;

	public function render()
	{
		return [
			$this->cardHeader('finance.annual-interests-late-payments'),
			_Rows(
				_InputNumber(__('finance.annual-interest-percent'))->step(0.01)
					->name('late_interest')
					->rIcon('<span>%</span>'),
				_InputNumber('finance.nb-of-days-before-charging-interest')->step(1)
					->name('late_days')
					->default(30),
				_SubmitButton()
					->refresh('chart-of-accounts-balances'),

			)->class('p-4')
		];
	}

	public function rules()
	{
		return [
			'late_interest' => 'required',
			'late_days' => 'required',
		];
	}
}
