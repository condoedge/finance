<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\FundQuote;
use Kompo\Form;

class FundQuoteParkingForm extends Form
{
	public $model = FundQuote::class;

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
		$displayedFraction = $this->model->fractions ? round($this->model->fractions, 2) : null;
		$displayedShare = $this->model->fractions ? round($this->model->fractions / $this->totalFractions * 100, 2) : null;

		return _FlexBetween(
			$this->model->unit_name ?
				_Rows(
					_Hidden('unit_id')->value($this->model->unit_id),
					_Html($this->model->unit_name)->class('font-bold'),
				)->class('mb-0 flex-auto') :
				_Select()->name('unit_id')
					->class('mb-0 flex-auto')
					->options(
						currentUnionUnitsOptions()
					)
					->run('calculateFundQuoteTotals'),
			$this->nbBox($this->model->parking_nb),
			_Input()->name('fractions', false)
				->type('number')
				->class('unit-fund-quote text-center mb-0 w-24 shrink-0')
				->inputClass('text-right')
				->value($displayedFraction)
				->run('calculateFundQuoteTotals'),
			_Html($displayedShare.' %')->class('unit-fund-quote-pct text-center w-24 shrink-0'),
			$this->deleteFundQuote()->class('text-gray-600 text-center w-14 shrink-0')->attr(['tabindex' => -1])
				->run('calculateFundQuoteTotals'),
		)->class('px-2 py-1 space-x-2');
	}

	protected function nbBox($label)
	{
		return _Html($label ? round($label) : '-')->class('text-sm w-20 shrink-0 text-center text-level1');
	}

	protected function deleteFundQuote()
	{
		return $this->model->id ?

			_DeleteLink()->byKey($this->model)->emit('deleted') :

			_Link()->icon(_Sax('trash',20))->emit('deleted');
	}

	public function rules()
	{
		return [
			'fractions' => 'required',
		];
	}
}
