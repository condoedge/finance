<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use App\View\Modal;

class ResetFinancialDataForm extends Modal
{
	public function render()
	{
		return _Rows(
			_Html('finance.this-action-will-erase1')->class('text-xl font-bold'),
			_Html(__('finance.this-action-will-erase2').'<br>'.__('finance.this-action-will-erase3'))->icon('exclamation-circle')->class('p-4 rounded-lg bg-danger bg-opacity-25'),
			_FlexBetween(
				_Button('finance.reset-my-financial-data')->selfPost('resetFinancialData')->redirect('finance.setup')
					->alert('finance.all-data-erased'),
				_Button('Cancel')->outlined()->closeModal(),
			)
		)->class('p-4 space-y-4');
	}

	public function resetFinancialData()
	{
		Union::find(currentUnion()->id)->resetFinancialData();
	}
}
