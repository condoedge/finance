<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Condo\Unit;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Acompte;
use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\Invoice;
use App\View\Modal;

class PaymentAcompteAddForm extends Modal
{
	protected $_Title = 'finance.view-advance-payments';
	protected $_Icon = 'cash';

	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

    protected $panelId = 'units-acomptes-mini-table';

	public function handle()
	{
        if (!currentUnion()->acceptsFinanceChange(request('transacted_at'))) {
            abort(403, balanceLockedMessage(currentUnion()->latestBalanceDate()));
        }

		if (!request('amount')) {
			abort(403, __('finance.cant-enter-zero-payment'));
		}

		$unit = Unit::findOrFail(request('unit_id'));

		Acompte::createForUnit(
			$unit,
			request('account_id'),
            request('transacted_at'),
            request('amount'),
            request('payment_method'),
            request('description'),
        );

		return $this->getAcomptesMiniTable($unit->id);
	}

	public function headerButtons()
	{
		return _SubmitButton('finance.record-payment')->inPanel($this->panelId)
			->alert('finance.advance-payment-added-to-unit!');
	}

	public function body()
	{
		return _Columns(
			_Rows(
	            _Select('Unit')->name('unit_id')->options(currentUnion()->unitOptions())->placeholder('finance.all-units')
	            	->getElements('getAcomptesMiniTable')->inPanel($this->panelId),

				_Date('finance.payment-date')->name('transacted_at')->default(date('Y-m-d')),

				_Input('Amount')->name('amount')->type('number')->step(0.01),

				GlAccount::cashAccountsSelect(),

				Entry::paymentMethodsSelect(),

				_Textarea('Description')->name('description'),
			),
			_Rows(
				_Panel(
					$this->getAcomptesMiniTable()
				)->id($this->panelId)
				->class('overflow-y-auto mini-scroll')
				->style('height:calc(95vh - 100px)')
			)
		);
	}

	public function rules()
	{
		return [
			'transacted_at' => 'required|date',
			'unit_id' => 'required',
			'account_id' => 'required',
			'amount' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
			'payment_method' => 'required',
		];
	}

	public function getAcomptesMiniTable($unitId = null)
	{
		$unitIds = $unitId ? collect([$unitId]) : currentUnion()->units()->pluck('units.id');

		$acomptes = Acompte::whereIn('unit_id', $unitIds)->orderBy('unit_id')->orderByDesc('created_at')->get()->groupBy('unit_id');

		if (!$acomptes->count()) {
			return _Html('finance.no-advance-payment-for-unit');
		}

		return _Rows(
			$acomptes->map(fn($acomptes, $unitId) => _Rows(
				_Html(__('Unit').' '.Unit::find($unitId)->name)->class('mt-2 font-semibold'),
				_Rows(
					$acomptes->map(
						fn($acompte) => _FlexBetween(
							_Html(carbon($acompte->transaction?->transacted_at)?->translatedFormat('d M Y')),
							_Currency($acompte->amount),
						)->class('px-4 py-2 border-b border-gray-100')
					)
				)
			))
		);
	}
}
