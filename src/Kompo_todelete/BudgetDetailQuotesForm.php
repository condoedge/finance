<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\BudgetDetail;
use Condoedge\Finance\Models\BudgetDetailQuote;
use App\View\Modal;

class BudgetDetailQuotesForm extends Modal
{
	public $class = 'overflow-y-auto mini-scroll';
	public $style = 'max-height: 95vh';

	public $model = BudgetDetail::class;

	protected $refresh = true;

	protected $_Title = 'finance.edit-fractions';
	protected $_Icon = 'cash';

	public function authorize()
	{
		if ($bdqs = request('budgetDetailQuotes')) {
			if(collect($bdqs)->map(fn($bdq) => $bdq['unit_id'])->unique()->count() < collect($bdqs)->count()) {
				abort(403, __('You are trying to enter duplicate fractions for the same unit'));
			}
		}

		return true;
	}

	public function completed()
	{
		$this->model->load('budgetDetailQuotes');

		//Calculating percentages for an easier query when calculating budget amounts...
		\DB::transaction(function(){

			$totalFractions = $this->model->budgetDetailQuotes->sum('fractions');

	    	$this->model->budgetDetailQuotes->each(function($bdq) use ($totalFractions) {

	    		$bdq->calc_pct = $totalFractions ? ($bdq->fractions / $totalFractions) : 0;
	    		$bdq->save();

	    	});
	    });
	}

	public function headerButtons()
	{
		return [
			_SubmitButton('general.save')->closeModal(),
		];
	}

	public function body()
	{
		if (!$this->model->budgetDetailQuotes()->count()) {
			$initialBdQuotes = $this->model->fund->fundQuotes()->count() ?
				$this->model->fund->fundQuotes()->get()->map(function($fundQuote){
					$bdQuote = new BudgetDetailQuote();
					$bdQuote->unit_id = $fundQuote->unit_id;
					$bdQuote->fractions = $fundQuote->fractions;
					return $bdQuote;
				})->values() :
				currentUnionUnits()->map(function($unit){
					$bdQuote = new BudgetDetailQuote();
					$bdQuote->unit_id = $unit->id;
					$bdQuote->fractions = $unit->totalSharePct() * 100;
					return $bdQuote;
				})->values();
			$this->model->setRelation('budgetDetailQuotes', $initialBdQuotes);
		}

		//Adding extra infos and sorting
		$this->model->setRelation('budgetDetailQuotes', $this->model->budgetDetailQuotes->map(function($bdQuote){
			$unit = $bdQuote->unit;
			$bdQuote->unit_name = $unit->name;
			$bdQuote->unit_pct = $unit->share_pct * 100;
			$bdQuote->parking_pct = $unit->extras()->parking()->sum('share_pct') * 100;
			$bdQuote->storage_pct = $unit->extras()->storage()->sum('share_pct') * 100;
			return $bdQuote;
		})->sortBy('unit_name')->values());

		$totalFractions = round($this->model->budgetDetailQuotes->sum('fractions'), 4);

		return _Rows(
			_Rows(
				$this->multiFormDecorators(
					_Html('Unit'),
					_Html('% '.'Unit')->class('text-xs text-level1'),
					currentUnion()->isHorizontal() ? _Html() : _Html('Parking')->class('text-xs text-level1'),
					currentUnion()->isHorizontal() ? _Html() : _Html('Storage')->class('text-xs text-level1'),
					_Html('Fraction'),
					_Html('% '.'Fund'),
				)->class('border-b'),
				_MultiForm()->noLabel()->name('budgetDetailQuotes')->preloadIfEmpty()
		            ->formClass(BudgetDetailQuoteForm::class, [
		                'total_fractions' => $totalFractions,
		            ])
		            ->addLabel('finance.add-custom-quote', 'icon-plus', 'mt-2 text-sm text-greenmain pb-20')
		            ->class('overflow-y-auto mini-scroll')
		            ->style('max-height: calc(95vh - 240px)'),
				$this->multiFormDecorators(
					_Html('Total'),
					_Html(),
					_Html(),
					_Html(),
					_Html($totalFractions)->id('fund-quote-total-fractions'),
					_Html('100 %'),
				)->class('border-t'),
		    )->class('bg-level3 bg-opacity-5 rounded-lg')
		)->class('mb-4');

	}

	protected function multiFormDecorators($col1, $col2, $col3, $col4, $col5, $col6)
	{
		return _FlexBetween(
			$col1->class('flex-auto'),
			$col2->class('w-16 shrink-0'),
			$col3->class('w-16 shrink-0'),
			$col4->class('w-16 shrink-0'),
			$col5->class('w-28 shrink-0 text-center'),
			$col6->class('w-28 shrink-0 text-center'),
			_Html()->class('w-12 shrink-0'),
		)->class('px-2 py-2 text-sm font-bold border-cyan-100 space-x-2');
	}
}
