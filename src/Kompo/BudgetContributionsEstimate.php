<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use Kompo\Table;
use Kompo\Elements\Element;

class BudgetContributionsEstimate extends Table
{
	protected $budgetId;
	protected $budget;

	protected $isApproved;
	protected $budgetDates;
	protected $remainingDates;

	public $perPage = 10;

	public $containerClass = 'container-fluid';

	public $itemsWrapperClass = 'overflow-x-auto mini-scroll';
	public $class = 'mb-10';

	public function created()
	{
		//Budget infos
		$this->budgetId = $this->parameter('budget_id');
		$this->budget = Budget::with('invoices')->findOrFail($this->budgetId);
		$this->budgetDates = $this->getBudgetDates();
		$this->remainingDates = $this->budget->getRemainingDates();

		//Macros
        Element::macro('openAnnualModal', $this->annualModalMacro());
		Element::macro('contributionClass', function(){
            return $this->asCurrency()
            	->class('text-sm level1 whitespace-nowrap');
        });
	}

	protected function getBudgetDates()
	{
		return $this->budget->getContributionDates();
	}

	protected function annualModalMacro()
	{
		return function($unit, $budget){
            return $this->get('contribution-annual.estimate', [
				'unit_id' => $unit->id,
				'budget_id' => $budget->id,
			])->inModal();
        };
	}

	public function query()
	{
		return $this->budget->union->units();
	}

	public function top()
	{
		return _Rows(
			_FlexBetween(
				_Breadcrumbs(
	                _Link('finance.back-to-budget')->href('budget.view', ['id' => $this->budgetId]),
	                _Html('finance.view-the-contributions'),
	            ),
	            $this->topRightButtons(),
			)->class('mb-6'),
			!$this->budget->addedFundsAdhoc()->count() ? null : new BudgetContributionsRegenerationForm($this->budgetId),
			_Rows(
				_Flex(
					_Html('finance.yearly-budget')->class('text-level1'),
					_Currency($this->budget->getRevenue()),
					_Html('finance.invoices-created')->class('text-level1'),
					_Currency($this->budget->getRealRevenue()),
				)->class('font-bold text-xl space-x-4 mb-4'),
				_Flex(
					array_merge([
						_Rows(
							_Html('Total')->class('font-bold'),
							_Html('Status')->class('text-xs text-gray-600'),
						)->class('flex-1'),
						], $this->budgetDates->map(
							fn($date, $month) => $this->topStats($date, $month)
						)->toArray(), [
							_Rows(
								_Html($this->getBudgetAmount())->contributionClass(),
								_Html('all-year')->class('text-xs'),
							)->class('font-bold flex-1')
						],
					)
				)->class('mb-4 space-x-4')
				->alignStart(),
				_Html('finance.upon-approval-contributions')
					->class('text-sm text-gray-600')->icon('question-mark-circle')
			)->class('dashboard-card container-fluid p-4 mb-6')
			->class('overflow-x-auto mini-scroll'),
		);
	}

	protected function topRightButtons()
	{
		return _FlexEnd4(
			$this->budget->isApproved() ?

				_Link('finance.see-actual-contributions')->href('budget-preview.view', [
					'budget_id' => $this->budgetId
				]) :

				_Dropdown('finance.good-approve-budget')->button()
					->submenu(
						_Link('finance.approve-and-create-contributions')->class('px-4 py-2 font-semibold border-b border-gray-100')
							->selfPost('approveBudget', [
								'id' => $this->budgetId
							])->inModal(),
						_Link('finance.approve-without-creating-contributions')->class('px-4 py-2 text-sm')
							->selfPost('approveWithout', [
								'id' => $this->budgetId
							])->refresh()
					)->alignRight()

		);
	}

	protected function topStats($date, $month)
	{
		return _Rows(
			_Html($this->getBudgetAmount(null, $date, $month))->contributionClass(),
			_Html($date->format('d M y'))->class('text-xs text-gray-600 whitespace-nowrap'),
			$this->contributionLabel($date),
		)->class('flex-1');
	}

	protected function getBudgetAmount($unit = null, $date = null, $month = null)
	{
		return $this->budget->getRevenue($unit, null, $month);
	}

	public function headers()
	{
		return array_merge([
				_Th('Unit'),
			], $this->budgetDates->map(
				fn($date) => _Th($date->format('M y'))
			)->toArray(), [
				_Th('Total')->class('font-bold text-right'),
			],
		);
	}

	public function render($unit)
	{
		return _TableRow(
			...$this->budgetDates->map(
				fn($date, $month) => $this->invoiceCell($unit, $date, $month)
			)->prepend(
				_Link($unit->display)->openAnnualModal($unit, $this->budget)
			)->push(
				_Link(
					$this->getBudgetAmount($unit)
				)->contributionClass()
				->class('font-bold block text-right')
				->openAnnualModal($unit, $this->budget)
			)
		);
	}

	protected function invoiceCell($unit, $date, $month)
	{
		return $this->monthlyModalAction(
			_Rows(
				_Html(
					$this->getBudgetAmount($unit, $date, $month)
				)->contributionClass(),
				$this->contributionLabel($date),
			)->class('cursor-pointer'),
			$unit,
			$date,
			$month
		);
	}

	protected function monthlyModalAction($komponent, $unit, $date, $month)
	{
		return $komponent->get('contribution.estimate', [
			'unit_id' => $unit->id,
			'budget_id' => $this->budget->id,
			'month' => $month,
		])->inModal();
	}

	protected function contributionLabel($date)
	{
		return $this->remainingDates->contains($date) ? $this->tbcLabel() : $this->missedLabel();
	}

	protected function checkedLabel($label = null)
	{
		return _Html($label ?: 'Created')->icon('icon-check')->class('text-xs text-positive whitespace-nowrap');
	}

	protected function missedLabel()
	{
		return _Html('Missed')->icon('icon-times')->class('text-xs text-danger whitespace-nowrap');
	}

	protected function tbcLabel()
	{
		return _Html('TBC')->class('text-xs text-info');
	}

	public function approveBudget($id)
	{
		$budget = Budget::findOrFail($id);

		if ($budget->getMissingDates()->count()) {
			return new BudgetContributionAdjustmentsForm($budget->id);
		}

		if ($budget->attemptMarkingApproved()) {
        	return new BudgetApprovalConfirmation($budget->id);
		}else{
        	return new BudgetDenialConfirmation($budget->id);
		}
	}

	public function approveWithout($id)
	{
		$budget = Budget::findOrFail($id);

		$budget->justMarkApproved();
	}
}
