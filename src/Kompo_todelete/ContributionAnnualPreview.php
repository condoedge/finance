<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;

class ContributionAnnualPreview extends ContributionAnnualEstimate
{
	protected $invoices;

	public function created()
	{
		parent::created();

		$this->invoices = $this->budgetId ?

			$this->budget->getInvoicesQuery($this->unit)->get() :

			static::getInvoices($this->unitId);

		$this->dates = $this->invoices->map->invoiced_at->unique()->sort();

		$this->totals = $this->dates->map(
			fn($date) => $this->invoices->first(fn($invoice) => $invoice->invoiced_at == $date)->total_amount
		);

		$this->total = $this->totals->sum();
	}

	protected function getAmountsPerDates($fund)
	{
		return $this->dates->map(
			fn($date) => $this->invoices->first(
							fn($invoice) => $invoice->invoiced_at == $date
						)->invoiceDetails->first(
							fn($invoiceDetail) => $invoiceDetail->fund_id == $fund->id
						)?->price ?: 0
		);
	}

	public static function getInvoices($unitId)
	{
		return Invoice::with('invoiceDetails')->forUnit($unitId)
			->whereBetween('invoiced_at', [
				currentUnion()->currentFiscalYearStart(),
				currentUnion()->currentFiscalYearStart()->copy()->addYears(1)->addDays(-1),
			])->get();
	}

	public function bottom()
	{
		return _FlexEnd4(
			_Link('export-pdf')->outlined()->href('print.contribution-annual.preview', [
				'unit_id' => $this->unitId,
				'budget_id' => $this->budgetId,
			])->inNewTab(),
			_Button('finance.send-by-email')->selfGet('getSendContributionByEmail')->inModal(),
		)->class('mt-4 hide-in-print');
	}

	public function getSendContributionByEmail()
	{
		return new ContributionSendingAnnualModal($this->unitId);
	}
}
