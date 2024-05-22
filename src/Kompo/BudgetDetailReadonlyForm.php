<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Budget;
use Condoedge\Finance\Models\BudgetDetail;
use Condoedge\Finance\Models\Fund;
use Kompo\Form;

class BudgetDetailReadonlyForm extends Form
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
    }

	public function render()
	{
		return [
			_Html($this->model->account?->display),
			_Html($this->model->amount ? abs($this->model->amount) : 0),
			$this->lastYearAccountAmounts($this->model->account_id),
			_Link()->icon('cog')->class('text-lg text-gray-300 mt-1')
				->selfGet('getBudgetDetailQuotesReadonly', ['id' => $this->model->id])
				->inModal(),
			_Html()
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
}
