<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;
use Kompo\Form;

class BudgetContributionAdjustmentForm extends Form
{
	public $model = Invoice::class;

	public function render()
	{
		return [
			_Rows(
				_Hidden('unit_id')->value($this->model->unit_id),
				_Html($this->model->unit_name),
			),
			_Currency($this->model->unpaid),
			_Currency($this->model->paid),
			_Input()->name('adjustment', false)
				->type('number')
				->default(round($this->model->unpaid - $this->model->paid, 2))
				->class('mb-0'),
		];
	}

	public function rules()
	{
		return [
			'adjustment' => 'required|numeric',
		];
	}
}