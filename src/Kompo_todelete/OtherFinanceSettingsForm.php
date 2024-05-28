<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use App\View\Traits\IsDashboardCard;
use Kompo\Form;

class OtherFinanceSettingsForm extends Form
{
    use IsDashboardCard;

	public $model = Union::class;

	public function render()
	{
		return [
			$this->cardHeader('finance.other-finance-settings'),
			_Rows(
				_Toggle('finance.bm-need-to-approve-bills')
					->name('board_approves_bills')
					->submit(),
			)->class('p-4')
		];
	}
}
