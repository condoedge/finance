<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\BudgetDetail;
use Condoedge\Finance\Models\BudgetDetailQuote;
use App\View\Modal;

class BudgetDetailSettingsForm extends Modal
{
	public $class = 'overflow-y-auto mini-scroll';
	public $style = 'max-height: 95vh;width:468px;max-width:100vw';

	public $model = BudgetDetail::class;

	protected $_Title = 'finance.edit-fractions';
	protected $_Icon = 'cash';

	public function handle()
	{
		if (request('exclude_amount')) {
			$this->model->excludeFromContributions();
		} else {
			$this->model->includeInContributions();
		}
	}

	public function body()
	{
		return _Rows(
			_Rows(
				_WarningMessage(
					_Html('finance.if-specified-fractions-this-will-delete-them')
				),
				_Toggle('finance.exclude-this-budget-item-from-contributions')->name('exclude_amount', false)
					->value($this->model->budgetDetailQuotes()->where('calc_pct', 0)->count() == currentUnion()->units()->count()),
			)->class('space-y-4 card-gray-100 p-4'),

			_FlexEnd(
				_SubmitButton('general.save')->closeModal(),
			)
		);
	}
}
