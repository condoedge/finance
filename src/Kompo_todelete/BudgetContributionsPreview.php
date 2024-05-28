<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Budget;
use Kompo\Table;

class BudgetContributionsPreview extends BudgetContributionsEstimate
{
	protected function getBudgetDates()
	{
		return $this->budget->getInvoiceDates();
	}

	protected function annualModalMacro()
	{
		return function($unit, $budget){
            return $this->get('contribution-annual.preview', [
				'unit_id' => $unit->id,
				'budget_id' => $budget->id,
			])->inModal();
        };
	}

	protected function monthlyModalAction($element, $unit, $date, $month)
	{
		$invoice = $unit->contributions->filter(
            fn($invoice) => ($invoice->invoiced_at == $date)
        )->first();

		return $element->get('contribution.preview', [
			'unit_id' => $unit->id,
			'invoice_id' => $invoice->id,
		])->inModal();
	}

	public function query()
	{
		return $this->budget->union->units()->with('contributions');
	}

	protected function topRightButtons()
	{
		return _FlexEnd4(
			_Link('finance.see-estimates')->href('budget-estimate.view', [
				'budget_id' => $this->budgetId
			]),
			_Button('finance.budget-approved')->icon('icon-check')->outlined(),
		)->class('space-x-4');
	}

	protected function getBudgetAmount($unit = null, $date = null, $month = null)
	{
		return $this->budget->getRealRevenue($unit, $date);
	}

	protected function contributionLabel($date)
	{
		return $this->checkedLabel(
			$this->budget->getContributionDates()->contains($date) ? '' : 'Adjustment'
		);
	}
}
