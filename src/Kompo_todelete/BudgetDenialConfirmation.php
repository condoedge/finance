<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use App\View\Modal;

class BudgetDenialConfirmation extends Modal
{
	public $class = 'overflow-y-auto mini-scroll bg-gray-100 rounded-2xl';
	public $style = 'max-height: 95vh;min-width:400px';

	public $model = Budget::class;

	protected $_Title = 'Error';
	protected $_Icon = 'x-circle';

	public function headerButtons()
	{
		return [
			_Button('Cancel')->outlined()->closeModal(),
		];
	}

	public function body()
	{
		return [
			_Html('finance.another-budget-approved')
				->class('mb-4'),
			_Div(
				_Link('finance.see-all-budgets')
					->href('budget')->inNewTab()
					->closeModal(),
			)
		];
	}

}
