<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Condo\Unit;
use Condoedge\Finance\Models\Budget;
use Kompo\Table;

class ContributionMonthlyEstimate extends Table
{
	public $class = 'overflow-y-auto mini-scroll w-full sm:w-4xl p-4 sm:p-8 card-white !mb-0';
	public $style = 'max-height: 95vh';

	public $itemsWrapperClass = 'tableLight overflow-x-auto mini-scroll';

	protected $unitId;
	protected $unit;
	protected $union;

	protected $budgetId;
	protected $budget;
	protected $month;

	protected $total;

	protected $title = 'finance.preview-contribution';
	protected $noTitle = false;
	protected $contributionLabel = 'finance.monthly-contribution';

	public function created()
	{
		$this->noTitle = $this->prop('no_title');

		$this->unitId = $this->prop('unit_id');
		$this->unit = Unit::findOrFail($this->unitId);
		$this->union = $this->unit->union;


		$this->budgetId = $this->prop('budget_id');
		$this->budget = $this->budgetId ? Budget::findOrFail($this->budgetId) : null;

		$this->month = $this->prop('month');

		$this->total = $this->budgetId ? $this->budget->getRevenue($this->unit, null, $this->month) : 0;
	}

	public function query()
	{
		return $this->union->getFunds();
	}

	public function top()
	{
		return _Rows(
			$this->noTitle ? null : _FlexBetween(
				_Html($this->title)->class('text-2xl font-black'),
				$this->contributionActionsEls(),
			)->class('mb-8'),
			_FlexBetween(
				_UnionUnitOwnersCard($this->union, $this->unit),
				_Rows(
					_FlexBetween(
						_Html()->icon(_sax('dollar-circle',50))->class('text-level3 text-opacity-50'),
						_Rows(
							_Html($this->contributionLabel)->class('text-xs'),
							_Currency($this->total)
								->class('text-xl font-bold'),
						)->class('text-right')
					)->class('card-gray-100 px-6 py-4 space-x-4'),
					_FlexBetween(
						$this->contributionInfos(),
					)->class('text-sm p-4 space-x-4'),
				)
			)->class('mb-8 md:space-x-8 flex-wrap')
		);
	}

	protected function contributionActionsEls()
	{
		return;
	}

	protected function contributionInfos()
	{
		return [
			_Rows(
				_Html('finance.Contribution'),
				_Html('finance.date'),
				_Html('finance.due-date'),
			)->class('space-y-2'),
			_Rows(
				$this->invoiceLink(),
				_Html($this->invoiceDate()->translatedFormat('d M Y')),
				_Html($this->invoiceDate()->translatedFormat('d M Y')),
			)->class('space-y-2 text-right'),
		];
	}

	protected function invoiceLink()
	{
		return _Html('finance.not-created-yet');
	}

	protected function invoiceDate()
	{
		return $this->budget->getContributionDates()[$this->month];
	}

	public function headers()
	{
		return [
			_Th('Fund'),
			_Th('Total')->class('text-right'),
		];
	}

	public function footers()
	{
		return $this->getTableRow(
			'Total',
			$this->total,
		)->class('font-bold');
	}

	public function render($item)
	{
		return $this->getTableRow(
			$item->name,
			$this->budget->getRevenue($this->unit, $item, $this->month),
		);
	}

	protected function getTableRow($name, $amount)
	{
		return _Tr(
			_Html($name)->class('text-right'),
            _Currency($amount)->class('text-right'),
        );
	}
}
