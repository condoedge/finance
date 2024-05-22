<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Budget;
use Condoedge\Finance\Models\BudgetDetail;
use Condoedge\Finance\Models\Fund;
use Kompo\Form;

class BudgetDetailForm extends Form
{
	public $model = BudgetDetail::class;

    protected $budgetId;
    protected $fundId;
    protected $group;

    protected $accountOptions;

    public function created()
    {
        $this->budgetId = $this->store('budget_id');
        $this->fundId = $this->store('fund_id');
        $this->group = $this->store('group');

        $this->accountOptions = \Cache::remember('fundAccounts-'.$this->fundId.'-'.$this->group, 20,
        	fn() => Fund::with('union')->findOrFail($this->fundId)->getRelatedAccounts($this->group)->get()
            			->mapWithKeys(fn($account) => $account->getOption())
		);
    }

	public function beforeSave()
	{
		if (!request('abs_amount')) {
			abort(403, __('condo.please-add-the-missing-amount'));
		}

		$this->model->budget_id = $this->budgetId;
		$this->model->fund_id = $this->fundId;

		$this->model->amount = request('abs_amount') * $this->getSign();
	}

	public function afterSave()
	{
		if (!$this->model->account->isIncome() && is_null($this->model->excluded)) {
			$this->model->excludeFromContributions();
		}
	}

	protected function getSign()
	{
		return $this->group == GlAccount::GROUP_EXPENSE ? -1 : 1;
	}

	public function render()
	{
		$select = _Select(' ')->labelClass('absolute -right-2 -top-2')->name('account_id')
				->class('mb-0 w-2xl')->style('min-width:300px;max-width:25vw')
				->options(
					$this->accountOptions
				)
				->submit()
				->onSuccess(fn($e) => $e->run('calculateFundTotals'))
				->onError(fn($e) => $e->alert('condo.please-add-an-amount', false));

		return [
			$this->model->id ? $select : $select->focusOnLoad(),
			_Input(' ')->labelClass('absolute -right-2 -top-2')->name('abs_amount', false)
				->type('number')->icon(
					$this->model->excluded ?
						_Svg('ban')->class('text-red-700') :
						_Html('$')
				)
				->class('text-right mb-0 w-36')
				->inputClass('text-right input-number-no-arrows budget-detail-'.$this->group)
				->value($this->model->amount ? abs($this->model->amount) : null)
				->submit()->debounce(1000)->alert('general.saved!')->run('calculateFundTotals'),
			$this->lastYearAccountAmounts($this->model->account_id),
			!$this->model->id ? _Html() :
				_TripleDotsDropdown()->submenu(
					_Link('finance.edit-fractions')->class('px-4 py-2 whitespace-nowrap')
						->get('budget-detail-quotes.form', ['id' => $this->model->id])
						->inModal(),
					_Link('finance.exclude-from-contributions')->class('px-4 py-2 whitespace-nowrap')
						->selfUpdate('getBudgetDetailSettings', ['id' => $this->model->id])
						->inModal(),
				)->class('text-sm')
				->alignRight(),
			$this->deleteLinkBudgetDetail()->class('text-gray-600'),
		];
	}

	protected function lastYearAccountAmounts($accountId)
	{
		if (!$accountId) {
			return _Html('0 / 0')->class('text-right');
		}

		$account = GlAccount::findOrFail($accountId);
		$budget = Budget::findOrFail($this->budgetId);

		$lastYearAmount = round($account->getBodBalanceFor($budget->fiscal_year_start) - $account->getBodBalanceFor($budget->fiscal_year_start->addYears(-1)));

		$previousBudget = $budget->getPreviousBudget();
		$lastYearBudget = $previousBudget ? abs(round($previousBudget->budgetDetails()->where('account_id', $accountId)->sum('amount'))) : 0;

		return _Html($lastYearAmount.' / '.$lastYearBudget)->class('text-right');
	}

	protected function deleteLinkBudgetDetail()
	{
		return $this->model->id ?

			_Link()->icon(_Sax('trash',20))->selfDelete('deleteBudgetDetail', ['id' => $this->model->id])->emitDirect('deleted') :

			_Link()->icon(_Sax('trash',20))->emitDirect('deleted');
	}

	public function rules()
	{
		return [
			'account_id' => 'required',
			//'abs_amount' => 'required',
		];
	}
}
