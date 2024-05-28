<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\BudgetDetailQuote;
use Kompo\Form;

class BudgetDetailQuoteForm extends Form
{
	public $model = BudgetDetailQuote::class;

	protected $totalFractions;

	public function created()
	{
		$this->totalFractions = $this->store('total_fractions');
	}

	public function beforeSave()
	{
		$this->model->fractions = request('fractions');
	}

	public function render()
	{
		$displayedFraction = $this->model->fractions ? round($this->model->fractions, 4) : 0;
		$displayedShare = ($this->model->fractions && $this->totalFractions) ? round($this->model->fractions / $this->totalFractions * 100, 4) : null;

		return _FlexBetween(
			$this->model->unit_name ?
				_Rows(
					_Hidden('unit_id')->value($this->model->unit_id),
					_Html($this->model->unit_name)->class('font-bold'),
				)->class('mb-0 flex-auto w-48') :
				_Select()->name('unit_id')
					->class('mb-0 flex-auto w-48')
					->options(
						currentUnionUnitsOptions()
					)
					->run('calculateFundQuoteTotals'),
			$this->percentBox($this->model->unit_pct),
			$this->percentBox($this->model->parking_pct),
			$this->percentBox($this->model->storage_pct),
			_Input()->name('fractions', false)
				->type('number')
				->class('unit-fund-quote text-center mb-0 w-28 shrink-0')
				->inputClass('text-right')
				->value($displayedFraction)
				->run('calculateFundQuoteTotals'),
			_Html($displayedShare.' %')->class('unit-fund-quote-pct text-center w-28 shrink-0'),
			$this->deleteFundQuote()->class('text-gray-600 text-center w-12 shrink-0')->attr(['tabindex' => -1])
				->run('calculateFundQuoteTotals'),
		)->class('px-2 py-1 space-x-2');
	}

	protected function percentBox($label)
	{
		return _Html($label ? (round($label, 4).' %') : '-')->class('text-sm w-16 shrink-0 text-center text-level1');
	}

	protected function deleteFundQuote()
	{
		return $this->model->id ?

			_DeleteLink()->byKey($this->model)->emit('deleted') :

			_Link()->icon('icon-trash')->emit('deleted');
	}

	public function rules()
	{
		return [
			'fractions' => 'required',
			'unit_id' => 'required',
		];
	}
}
