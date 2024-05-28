<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;

class ContributionMonthlyPreview extends ContributionMonthlyEstimate
{
	protected $invoiceId;
	protected $invoice;

	protected $title = 'finance.notice-of-assessment';

	public function created()
	{
		parent::created();

		$this->invoiceId = $this->parameter('invoice_id');
		$this->invoice = $this->invoiceId ? Invoice::findOrFail($this->invoiceId) : null;

		$this->budgetId = $this->invoice->budget_id; //for report preview

		$this->total = $this->invoice?->total_amount ?: 0;
	}

	public function query()
	{
		return $this->invoice->invoiceDetails;
	}

	protected function contributionActionsEls()
	{
		return _Link('finance.see-annual-contribution')->outlined()->class('hide-in-print')	
			->closeModal()->onSuccess(fn($e) => $e->selfGet('getYearlyContribution')->inModal());
	}

	public function getYearlyContribution()
	{
		return new ContributionAnnualPreview([
				'unit_id' => $this->unitId,
				'budget_id' => $this->budgetId,
			]);
	}

	public function bottom()
	{
		return _FlexEnd4(
			_Link('export-pdf')->outlined()->href('print.contribution.preview', [
				'unit_id' => $this->unitId,
				'invoice_id' => $this->invoiceId,
			])->inNewTab(),
			_Button('finance.send-by-email')->selfGet('getSendInvoiceByEmail')->inModal(),
		)->class('mt-4 hide-in-print');
	}

	protected function invoiceLink()
	{
		if (auth()->user()->isContact()) {
			return _Html($this->invoice->invoice_number);
		}

		return _Link($this->invoice->invoice_number)->class('underline')
			->href('invoices.stage', ['id' => $this->invoice->id])->inNewTab();
	}

	protected function invoiceDate()
	{
		return $this->invoice->invoiced_at;
	}

	public function render($invoiceDetail)
	{
		return $this->getTableRow(
			$invoiceDetail->name,
			$invoiceDetail->price
		);
	}

	public function getSendInvoiceByEmail()
	{
		return new ContributionSendingSingleModal($this->invoiceId);
	}
}
