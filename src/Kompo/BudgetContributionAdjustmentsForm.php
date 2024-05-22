<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use Condoedge\Finance\Models\Invoice;
use App\View\Modal;

class BudgetContributionAdjustmentsForm extends Modal
{
	public $class = 'overflow-y-auto mini-scroll lg:w-4xl xl:w-5xl';
	public $style = 'max-height: 95vh';

	public $model = Budget::class;

	protected $_Title = 'finance.approve-budget';
	protected $_Icon = 'cash';

	public function handle()
	{
		if (!$this->model->attemptMarkingApproved(request('adj_date'), request('adjustments'), request('billed_date'))) {
			return new BudgetDenialConfirmation($this->model->id);
		}

		return new BudgetApprovalConfirmation($this->model->id);
	}

	public function createAllContributions()
	{
		if ($this->model->attemptMarkingApproved()) {
        	return new BudgetApprovalConfirmation($this->model->id);
		}else{
        	return new BudgetDenialConfirmation($this->model->id);
		}
	}

	public function body()
	{
		return _Panel(
			_WarningMessage(
				_Html('finance.fiscal-year-already-started'),
                _Link()->class('underline'),
			)->class('mb-6'),
			_Columns(
				_Button('finance.fiscal-year-already-started-yes')
					->outlined()
					->toggleId('adjustments-table'),
				_Button('finance.fiscal-year-already-started-no')
					->selfPost('createAllContributions')
					->inPanel('contribution-modal-body'),
			)->class('mb-4'),
			_Rows(
				_TitleMini('finance.adjustment-contribution')
					->class('mb-2'),
				_FlexBetween(
					_Flex4(
						_Date()->name('adj_date', false)->default(date('Y-m-d'))->class('mb-0'),
						_Button('Recalculate')->outlined()->icon('refresh')
							->getElements('getAdjustmentsTable', null, true)
							->inPanel('adjustments-table-panel'),
					),
					_Button('finance.create-adjustment')
						->submit()->inPanel('contribution-modal-body'),
				),
				_Html('<small class="vlFormComment">'.__('finance.adjustment-will-be-calculated').'</small>')
					->class('mb-4'),

				_TitleMini('finance.when-will-adjustment-be-billed')->class('mb-2'),
				_Date()->name('billed_date', false)->default(carbon(date('Y-m-d'))->addDays(2)->format('Y-m-d'))
					->comment('finance.same-as-adjustment-date'),

				_TitleMini('finance.adjustment-amount-text')
					->class('mb-2'),
				_Panel(
					$this->getAdjustmentsTable(),
				)->id('adjustments-table-panel')
			)->id('adjustments-table')
			->class('bg-level3 bg-opacity-5 rounded-lg shadow p-4'),
		)->id('contribution-modal-body');
	}

	public function getAdjustmentsTable()
	{
		$adjustmentDate = request('adj_date') ?: date('Y-m-d');

		$missingDates = $this->model->getMissingDates($adjustmentDate);
		$lastYearBudget = $this->model->getPreviousBudget();

		return _MultiForm()->noLabel()->name('adjustments', false)
			->preloadIfEmpty()
            ->formClass(BudgetContributionAdjustmentForm::class)
            ->asTable([
            	'Unit',
            	__('finance.unpaid-missed'),
            	__('finance.paid-amount'),
            	__('finance.adjustment-amount'),
            ])
            ->noAdding()
            ->value(
            	currentUnionUnits()->map(function($unit) use ($missingDates, $lastYearBudget) {
					$invoice = new Invoice();
					$invoice->unit_id = $unit->id;
					$invoice->unit_name = $unit->name;
					$invoice->unpaid = $missingDates->sum(fn($cDate) =>
						$this->model->getRevenue($unit, null, $this->model->getMonthOfContributionDate($cDate))
					);
					$invoice->paid = $missingDates->sum(fn($cDate) =>
						$unit->getPreviouslyPaidAmount($cDate, $lastYearBudget)
					);
					return $invoice;
				})->values()
			);
	}

	public function rules()
	{
		return [
			'adj_date' => 'required',
			'billed_date' => 'required|after:tomorrow',
		];
	}
}
