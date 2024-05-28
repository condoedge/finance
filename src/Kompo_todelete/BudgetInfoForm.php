<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use App\View\Modal;

class BudgetInfoForm extends Modal
{
	public $model = Budget::class;

	protected $_Title = 'finance.budget-information';
	protected $_Icon = 'calculator';

	protected $union;

	public function created()
	{
		$this->union = currentUnion();
	}

	public function beforeSave()
	{
		$this->model->union_id = $this->union->id;
		$this->model->fiscal_year_start = request('time_period');
	}

	public function response()
	{
		return redirect()->route('budget.view', ['id' => $this->model->id]);
	}

	public function body()
	{
		if (!$this->union->totalSharesComplete()) {
			return [
				_WarningMessage(
					_Html('finance.total-share-not-100'),
					_Link('finance.lead-manager-has-to-add')
						->href('union.manager', [
							'id' => $this->union->id,
							'tab' => 5,
						])
				)
			];
		}

		return [
			_Select('finance.time-period')->name('time_period', false)
				->options($this->budgetDateOptions())
				->value(optional($this->model->fiscal_year_start)->format('Y-m-d'))
				->default($this->union->currentFiscalYearStart()->format('Y-m-d'))
				->comment('finance.time-period-text'),
			_Input('general.name')->name('name'),
			_Textarea('Description')->name('description'),
			_SubmitButton('general.save')
		];
	}

	protected function budgetDateOptions()
	{
		$currentDefaultDate = $this->union->currentFiscalYearStart();
		$lastYearDate = $currentDefaultDate->copy()->addYear(-1);
		$nextYearDate = $currentDefaultDate->copy()->addYear(1);
		$nextNextYearDate = $currentDefaultDate->copy()->addYear(2);

		$dateFormat = 'd M Y';

		return [
			$lastYearDate->format('Y-m-d') => $lastYearDate->format($dateFormat).' - '.$currentDefaultDate->copy()->addDays(-1)->format($dateFormat),
			$currentDefaultDate->format('Y-m-d') => $currentDefaultDate->format($dateFormat).' - '.$nextYearDate->copy()->addDays(-1)->format($dateFormat),
			$nextYearDate->format('Y-m-d') => $nextYearDate->format($dateFormat).' - '.$nextNextYearDate->copy()->addDays(-1)->format($dateFormat),
		];
	}

	public function rules()
	{
		return [
			'time_period' => 'required',
			'name' => 'required',
		];
	}
}
