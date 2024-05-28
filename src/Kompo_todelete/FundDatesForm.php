<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use Condoedge\Finance\Models\Fund;
use Condoedge\Finance\Models\FundDate;
use App\View\Form;

class FundDatesForm extends Form
{
	protected $budgetId;
	protected $budget;
	protected $fundId;
	protected $fund;

	protected $isReadonly = false;

	public function created()
	{
		$this->fundId = $this->store('fund_id');
		$this->fund = Fund::with('fundDates')->findOrFail($this->fundId);
		$this->budgetId = $this->store('budget_id');
		$this->budget = Budget::findOrFail($this->budgetId);

		$this->isReadonly = $this->prop('is_readonly');
	}

	public function render()
	{
		$fundInitialValue = $this->budget->getRevenue(null, $this->fund);

		return _Flex(
			collect(FundDate::months())->map(function($label, $month) use($fundInitialValue){

				$fundDate = $this->fund->fundDates->where('month', $month)->first();

				$monthName = 'month'.$month;
				$monthValue = $fundDate ? $fundDate->checked : 0;

				$el = $this->isReadonly ? null : 

						_Checkbox()->name($monthName)->value($monthValue)->class('mb-0')
							->class('fund-month-checkbox')->selfPost('checkFundDate', ['month' => $month])
							->run('calculateFundMonthlyTotals');

				return $this->monthRow(
					_Html($label),
					_Currency($fundDate ? ($fundInitialValue/$this->fund->fundDates->count()) : 0)
						->class('fund-month-value'),
					$el,
				)->class('fund-month-row');
			})->all() + [
				$this->monthRow(
					_Html('Total'),
					_Html($fundInitialValue)->class('mb-6')
						->class('fund-total-value'),
				)->class('flex-auto bg-gray-50')
			]
		)->class('mb-4 space-x-2 overflow-x-auto border border-gray-100 rounded');
	}

	public function checkFundDate($month, $check)
	{
		if ($check) {
			$fundDate = $this->fund->fundDates->where('month', $month)->first();

			if (!$fundDate) {
				$fundDate = new FundDate();
				$fundDate->month = $month;
			}

			$fundDate->checked = 1;
			$this->fund->fundDates()->save($fundDate);
		}else{
			FundDate::where('fund_id', $this->fund->id)->where('month', $month)->delete();
		}
	}

	protected function monthRow($first, $second, $third = null)
	{
		return _Rows(
			$first->class('text-xs font-bold whitespace-nowrap'),
			$second->class('text-sm text-gray-500 whitespace-nowrap'),
			$third,
		)->class('items-center p-2 pl-4 border-l border-gray-100');
	}
}