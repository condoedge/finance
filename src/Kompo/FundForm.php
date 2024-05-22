<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Fund;
use Condoedge\Finance\Models\FundDate;
use Condoedge\Finance\Models\FundQuote;
use App\View\Modal;

class FundForm extends Modal
{
	public $class = 'overflow-y-auto mini-scroll';
	public $style = 'max-height: 95vh';

	public $model = Fund::class;

	protected $refresh = true;

	public function created()
	{
		$this->_Title = $this->model->id ? 'finance.edit-fund' : 'finance.add-fund';
		$this->_Icon = 'cash';
	}

	public function beforeSave()
	{
		$this->model->union_id = currentUnion()->id;
		$this->model->type_id = request('type_id') ?: Fund::TYPE_CUSTOM;
	}

	public function completed()
	{
		if ($this->model->incomeAccount) {
			$this->model->incomeAccount->fund_id = null;
			$this->model->incomeAccount->save();
		}

		$incomeAccount = request('income_account_id') ?
			GlAccount::findOrFail(request('income_account_id')) :
			$this->model->newFundIncomeAccount(
				$this->model->fundQuotes()->count() ?
					__('finance.common-budget') :
					__('finance.special-contributions')
			);

		$incomeAccount->fund_id = $this->model->id;
		$incomeAccount->save();


		if (!$this->model->isDefaultFund()) {
			if (!($bnrAccount = $this->model->bnrAccount)) {
				$bnrAccount = $this->model->newFundBnrAccount(
					GlAccount::inUnionGl()->where('code', GlAccount::CODE_BNR_SPECIAL)->value('type')
				);
			}

			$bnrAccount->fund_id = $this->model->id;
			$bnrAccount->save();
		}
	}

	public function headerButtons()
	{
		return [
			_SubmitButton('general.save')->closeModal()->refresh('budget-view-id'),
		];
	}

	public function body()
	{
		$panelId = 'new-fund-panel-id';

		if (!$this->model->id && !request('name')) { //check that we aren't submitting
			return _Panel(
				_Columns(
					_HugeButton('finance.special-contributions', 'star-1')
						->getElements('getFundFormElements')
						->inPanel($panelId),
					_HugeButton('finance.common-budget', 'buildings-2')
						->getElements('getFundFormElements', ['withFundQuotes' => true])
						->inPanel($panelId),
					_HugeButton('finance.parking-fund', 'car')
						->getElements('getFundFormElements', ['withFundQuotes' => true, 'parkingType' => true])
						->inPanel($panelId),
					_HugeButton('finance.other-fund', 'box-1')
						->getElements('getFundFormElements')
						->inPanel($panelId),
				)
			)->id($panelId);
		}

		$withFundQuotes = $this->model->fundQuotes()->count() || request('fundQuotes');

		return $this->getFundFormElements($withFundQuotes, $this->model->type == Fund::TYPE_PARKING);
	}

	public function getFundFormElements($withFundQuotes = false, $parkingType = false)
	{
		$typeEl = $parkingType ? _Hidden('type_id')->value(Fund::TYPE_PARKING) : null; //defaults to TYPE_CUSTOM

		$fundQuotesMethod = !$withFundQuotes ? null : ($parkingType ? 'getFundParkingsElements' : 'getFundQuotesElements');

		return [
			_Columns(
				_Rows(
					$typeEl,
					_Translatable('Name')->name('name'),
					_Translatable('Description')->name('description')->asTextarea(),

					_Hidden()->name('income_account_id', false)->value($this->model->account?->id),

					$this->showFundAccount($this->model->incomeAccount, 'finance.related-income-account', 1),
					$this->showFundAccount($this->model->bnrAccount, 'finance.related-bnr-account', 2),

					/*_Rows(
						_TitleMini('finance.related-account')->class('mb-2'),

						$this->model->isDefaultFund() ?

							_Hidden()->name('income_account_id', false)->value($this->model->account?->id) :

							_Select('finance.related-account')
								->name('income_account_id', false)
								->options(
									GlAccount::inUnionGl()
										->income()
										->get()
										->reject(fn($account) => $account->fund_id && ($account->fund_id !== $this->model->id))
										->mapWithKeys(fn($account) => $account->getOption())
								)
								->value(optional($this->model->account)->id)
								->comment('finance.income-account-leave-blank'),

					)->class('card-gray-200 p-4'),*/


				)->class('mb-4')
				->col($fundQuotesMethod ? 'col-md-4' : ''),
				$fundQuotesMethod ? $this->{$fundQuotesMethod}()->col('col-md-8') : null,
			)->class($fundQuotesMethod ? 'lg:w-5xl xl:w-6xl' : 'lg:w-xl xl:w-2xl'),
		];
	}

	protected function showFundAccount($account, $label, $key)
	{
		if (!$this->model->id) {
			return;
		}

		$panelId = 'account-panel-fund-'.$key;

		$el = _Rows(
			_Html($label)->class('text-sm font-bold mb-2'),
			($account ? $account->getOptionLabel() : _Html($account?->display ?: 'finance.no-related-account'))->class('card-white p-4'),
		);

		if ($account) {
			$el = $el->selfUpdate('getAccountForm', [
				'id' => $account->id,
				'sub_code_id' => substr($account->code, 0, 2),
			])->inPanel($panelId);
		}

		return _Panel($el)->id($panelId)->class('card-gray-100 p-4 pb-0');
	}

	public function getAccountForm($id, $subCodeId)
	{
		return new AccountForm($id,[
            'sub_code_id' => $subCodeId,
        ]);
	}

	protected function getFundQuotesElements()
	{
		if (!$this->model->fundQuotes()->count()) {
			$this->model->setRelation('fundQuotes', currentUnionUnits()->map(function($unit){
				$fundQuote = new FundQuote();
				$fundQuote->unit_id = $unit->id;
				$fundQuote->fractions = $unit->totalSharePct() * 100;
				return $fundQuote;
			})->values());
		}

		//Adding extra infos
		$this->model->setRelation('fundQuotes', $this->model->fundQuotes->map(function($fundQuote){
			$unit = $fundQuote->unit;
			$fundQuote->unit_name = $unit->name;
			$fundQuote->unit_pct = $unit->share_pct * 100;
			$fundQuote->parking_pct = $unit->extras()->parking()->sum('share_pct') * 100;
			$fundQuote->storage_pct = $unit->extras()->storage()->sum('share_pct') * 100;

			return $fundQuote;
		})->sortBy('unit_name')->values());

		$totalFractions = round($this->model->fundQuotes->sum('fractions'), 2);

		return _Rows(
			_Rows(
				$this->multiFormDecorators(
					_Html('Unit'),
					_Html('finance.percent-unit')->class('text-xs'),
					currentUnion()->isHorizontal() ? _Html() : _Html('Parking')->class('text-xs'),
					currentUnion()->isHorizontal() ? _Html() : _Html('Storage')->class('text-xs'),
					_Html('Fraction'),
					_Html('finance.percent-fund'),
				)->class('border-b'),
				_MultiForm()->noLabel()->name('fundQuotes')->preloadIfEmpty()
		            ->formClass(FundQuoteForm::class, [
		                'total_fractions' => $totalFractions,
		            ])
		            ->addLabel('finance.add-custom-quote', 'icon-plus', 'mt-2 text-sm text-level1')
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
		    )->class('card-gray-100 p-4')
		)->class('mb-4');

	}

	protected function getFundParkingsElements()
	{
		if (!$this->model->fundQuotes()->count()) {
			$this->model->setRelation('fundQuotes', currentUnion()->units()->withCount('parkings')->get()
				->map(function($unit) {
					if ($union = $unit->representedUnion) {
						$unit->parkings_count = $union->units()->withCount('parkings')->get()->sum('parkings_count');
					}

					if (!$unit->parkings_count) {
						return;
					}
					$fundQuote = new FundQuote();
					$fundQuote->unit_id = $unit->id;
					$fundQuote->fractions = $unit->parkings_count;
					return $fundQuote;
				})->filter()->values()
			);
		}

		//Adding extra infos
		$this->model->fundQuotes->each(function($fundQuote){
			$unit = $fundQuote->unit;
			if ($union = $unit->representedUnion) {
				$unit->parkings_count = $union->units()->withCount('parkings')->get()->sum('parkings_count');
			}else{
				$unit->parkings_count = $unit->extras()->parking()->count();
			}
			$fundQuote->unit_name = $unit->name;
			$fundQuote->parking_nb = $unit->parkings_count;
		});

		$totalFractions = round($this->model->fundQuotes->sum('fractions'), 2);

		return _Rows(
			_Rows(
				$this->parkingDecorators(
					_Html('Unit'),
					_Html('finance.nb-parkings'),
					_Html('Fraction'),
					_Html('finance.percent-fund'),
				)->class('border-b'),
				_MultiForm()->noLabel()->name('fundQuotes')->preloadIfEmpty()
		            ->formClass(FundQuoteParkingForm::class, [
		                'total_fractions' => $totalFractions,
		            ])
		            ->addLabel('finance.add-custom-quote', 'icon-plus', 'mt-2 text-sm text-level1')
		            ->class('overflow-y-auto mini-scroll')
		            ->style('max-height: calc(95vh - 240px)'),
				$this->parkingDecorators(
					_Html('Total'),
					_Html(),
					_Html($totalFractions)->id('fund-quote-total-fractions'),
					_Html('100 %'),
				)->class('border-t'),
		    )->class('card-gray-100 p-4')
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
		)->class('px-2 py-2 text-sm font-bold border-level1 space-x-2 text-center');
	}

	protected function parkingDecorators($col1, $col2, $col3, $col4)
	{
		return _FlexBetween(
			$col1->class('flex-auto'),
			$col2->class('w-20 shrink-0'),
			$col3->class('w-24 shrink-0'),
			$col4->class('w-24 shrink-0'),
			_Html()->class('w-14 shrink-0'),
		)->class('px-2 py-2 text-sm font-bold border-level1 space-x-2 text-center');
	}

	public function rules()
	{
		return [
			'name' => 'required',
		];
	}
}
