<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use App\View\Form;

class UnionNfsFeesForm extends Form
{
	public $model = Union::class;

	public function render()
	{
		return _Rows(
			_CardHeader('finance.non-sufficient-funds-fee'),
			_Rows(
				_DollarInput('finance.non-sufficient-funds-fee-sub1')->step(0.01)
					->name('nfs_fees'),
				_SubmitButton()

			)->class('p-4')
		)->class('dashboard-card');
	}

	public function rules()
	{
		return [
			'nfs_fees' => 'required',
		];
	}
}
