<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Budget;
use Kompo\Form;

class BudgetView extends Form
{
	public $model = Budget::class;

	public $containerClass = 'container-ce';

	public $redirectTo = 'budget';

	protected $funds;

	public $id = 'budget-view-id';

	public function authorizeBoot()
	{
		return auth()->user()->can('view', $this->model);
	}

	public function created()
	{
		$this->funds = currentUnion()->funds;
	}

	public function render()
	{
		return [
			_FlexBetween(
				_Breadcrumbs(
	                _Link('finance.all-budgets')->href('budget'),
                    _Html($this->model->name),
	            ),
				_FlexEnd(
					_Button('finance.edit-budget-info')->get('budget-info.form', [
						'id' => $this->model->id
					])->inModal()->outlined(),

					_Link('finance.see-contributions')->button()
						->href(
							$this->model->isDraft() ?
								'budget-estimate.view' :
								'budget-preview.view', [
								'budget_id' => $this->model->id
							]),
				)->class('space-x-4'),
			)->class('mb-6'),
			_Columns(
				_Rows(
					$this->sectionTitle('Period')->class('mb-2'),
					$this->detailsCard(
						[
							_Html($this->model->period_label)->class('text-greenmain')
						]
					),
					$this->sectionTitle('Provisions')->class('mb-2'),
					$this->detailsCard(
						$this->funds->map(function($fund){
							return _FlexBetween(
								_Html($fund->name)->class('text-greenmain'),
								_Currency(
									$this->model->getAmount(null, $fund)
								)->class('whitespace-nowrap')
								->id('fund-'.$fund->id.'-total-balance')
							)->class('text-sm mb-2 px-2 py-1');
						})
					),
				)->col('col-lg-3'),
				_Rows(
					_FlexBetween(
						$this->sectionTitle('finance.distribution-by-funds'),
						_Link('finance.add-new-fund')->icon(_Sax('add',20))
							->class('text-sm text-greenmain font-bold')
							->get('funds.form')
							->inModal(),
					),
					_Rows(
						$this->funds->map(function($fund){
							return new BudgetFundDetails($fund->id, [
								'budget_id' => $this->model->id
							]);
						})
					)
				)->col('col-lg-9'),
			)
		];
	}

	protected function detailsCard()
	{
		return _Rows(func_get_args()[0])->class('dashboard-card mb-6 p-4');
	}

    protected function sectionTitle($label)
    {
        return _TitleMini($label)->class('mb-2');
    }

	protected function detailsTitle($label)
	{
		return _Html($label)->class('text-greenmain text-sm font-bold');
	}

    public function js()
    {
    	$expenseClass = '.budget-detail-'.GlAccount::GROUP_EXPENSE;
    	$incomeClass = '.budget-detail-'.GlAccount::GROUP_INCOME;


        return <<<javascript

function calculateFundTotals()
{
	$(".fund-details-card").each(function(){

		let fundIncome = getFundAmount($(this), "$incomeClass")
		let fundExpenses = getFundAmount($(this), "$expenseClass")

		$(this).find('.fund-total-revenue').html(asCurrency(fundIncome))
		$(this).find('.fund-total-expense').html(asCurrency(fundExpenses))
		$(this).find('.fund-total-provision').html(asCurrency(fundIncome - fundExpenses))

		let fundId = $(this).data('fund-id')

		$('#fund-'+fundId+'-total-revenue').html(asCurrency(fundIncome))
		$('#fund-'+fundId+'-total-balance').html(asCurrency(fundIncome - fundExpenses))

	})
}

function calculateFundMonthlyTotals()
{

	$(".fund-details-card").each(function(){

		let fundIncome = getFundAmount($(this), "$incomeClass")

		var checkedMonths = 0
		$(this).find('.fund-month-row').each(function(){
			if(hasCheckedMonth($(this))) {
				checkedMonths += 1
			}
		})

		$(this).find('.fund-month-row').each(function(){
			$(this).find('.fund-month-value').html(
				asCurrency( hasCheckedMonth($(this)) ? (fundIncome/checkedMonths) : 0 )
			)
		})

		$(this).find('.fund-total-value').html(asCurrency(fundIncome))

	})
}

function getFundAmount(that, inputClass){
	var amount = 0
	that.find(inputClass).each(function(){
		amount += parseFloat($(this).val())
	})
	return amount
}

function hasCheckedMonth(that){
	return that.find('.fund-month-checkbox input').eq(0).attr('aria-checked')
}
javascript;
    }
}
