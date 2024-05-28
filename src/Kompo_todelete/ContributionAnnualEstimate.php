<?php

namespace Condoedge\Finance\Kompo;

class ContributionAnnualEstimate extends ContributionMonthlyEstimate
{
	public $containerClass = 'container-fluid';
	public $class = 'overflow-y-auto mini-scroll p-4 sm:p-8 card-white';
	protected $contributionLabel = 'finance.annual-contribution';

	protected $dates;
	protected $totals;

	public function created()
	{
		parent::created();

		if (!$this->budget) { //for inherited ContributionAnnualPreview
			return;
		}

		$this->dates = $this->budget->getContributionDates();

		$this->totals = $this->dates->map(
			fn($date, $month) => $this->budget->getRevenue($this->unit, null, $month)
		);
	}

	protected function contributionInfos()
	{
		$hasDates = $this->dates && $this->dates->count();

		return [
			_Rows(
				_Html('Period'),
				_Html('finance.nb-payments'),
				_Html(''),
			)->class('space-y-2'),
			_Rows(
				!$hasDates ? _Html('N/A') : _Html($this->dates->first()->format('d M Y').' - '.$this->dates->last()->format('d M Y')),
				!$hasDates ? _Html('N/A') : _Html($this->dates->count()),
				_Html(''),
			)->class('space-y-2 text-right'),
		];
	}


	public function headers()
	{
		$parentHeaders = parent::headers();

		return array_merge([
				$parentHeaders[0],
			], $this->dates->map(
				fn($date) => _Th($date->format('M y'))->class('text-right')
			)->toArray(), [
				$parentHeaders[1],
			],
		);
	}

	public function render($fund)
	{
		$amountsPerDates = $this->getAmountsPerDates($fund);

		if ($amountsPerDates->sum()) {
			return $this->getTableRow(
				$fund->name,
				$amountsPerDates,
			);
		}
	}

	protected function getAmountsPerDates($fund)
	{
		return $this->dates->map(
			fn($date, $month) => $this->budget->getRevenue($this->unit, $fund, $month)
		);
	}

	public function footers()
	{
		return $this->getTableRow(
			'Total',
			$this->totals,
		)->class('font-bold');
	}

	protected function getTableRow($name, $amounts)
	{
		return _TableRow(
			$amounts->map(
				fn($amount) => _Currency($amount)->class('text-right text-sm whitespace-nowrap')
			)->prepend(
				_Html($name)
			)
			->push(
				_Currency($amounts->sum())->class('text-right text-sm whitespace-nowrap')
			)
        );
	}
}
