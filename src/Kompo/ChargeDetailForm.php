<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\ChargeDetail;
use Condoedge\Finance\Models\Tax;
use Kompo\Form;

class ChargeDetailForm extends Form
{
	public $model = ChargeDetail::class;

	public $class = 'align-top';

	protected $chargeableId;
	protected $chargeableType;
	protected $chargeable;

	protected $unionId;
	protected $union;

	protected $defaultAccounts;

	public function created()
	{
		$this->chargeable = request('chargeable') ? getModelFromMorphString(request('chargeable')) : null;
		$this->chargeableId = $this->chargeable?->id;
		$this->chargeableType = $this->chargeable ? $this->chargeable::getRelationType() : null;

		$this->unionId = $this->store('union_id');
		$this->union = Union::find($this->unionId);

		$this->defaultAccounts = $this->prop('default_accounts');
	}

	public function beforeSave()
	{
		Tax::setDefaultTaxes(request('taxes'));
	}

	public function afterSave()
	{
		$this->model->calculateAmountsChd();
	}

	public function render()
	{
		$defaultAccounts = $this->defaultAccounts;
		$usableAccounts = GlAccount::{$defaultAccounts}($this->unionId)->get();

		if ($this->chargeableId) {
			$name = $this->chargeable->{$this->chargeable::SEARCHABLE_NAME_ATTRIBUTE};
			$price = $this->chargeable->getMainPricePerUnit();
			$quantity = 1;
			$taxes = Tax::getDefaultTaxes();
			$defaultAccountId = GlAccount::inUnionGl($this->unionId)->where('code', $this->chargeable->gl_account_code)->value('id');
		} else {
			$name = $this->model->name_chd;
			$price = $this->model->price_chd;
			$quantity = $this->model->quantity_chd;
			$taxes = $this->model->id ? $this->model->taxes()->get() : Tax::getDefaultTaxes();
			$defaultAccountId = null;
		}

		$amount = $price * $quantity;
		$defaultGlAccountId = $this->model->gl_account_id ?: ($defaultAccountId ?: $usableAccounts->first()?->id);

		return [
			_Rows(
				_Input()->placeholder('finance.new-item-name')->name('name_chd')
					->default($name),
				_Select()->placeholder('finance.associated-account')->name('gl_account_id')
					->options(
						$usableAccounts->mapWithKeys(fn($account) => $account->getOption())
					)
					->default($defaultGlAccountId)
					->class('mb-0'),
			)->class('pl-4 w-72'),

			_Input()->placeholder('finance.item-description')->name('description_chd')->style('width: 20em'),

			_Rows(
				$this->model->getChargeableHiddenEls($this->chargeable),
				_FlexBetween(
					_Flex(
						_Input()->type('number')
							->name('quantity_chd')
							->default(1)
							->class('w-28 mb-0')
							->run('calculateTotals'),

						_Input()->type('number')
							->name('price_chd')
							->class('w-28 mb-0')
							->run('calculateTotals')
							->value($price),
					)->class('space-x-4'),

					_Currency($price)
						->class('item-total w-32 text-lg font-semibold text-level1 text-right')
				)->class('mb-4'),

				_FlexBetween(
					_MultiSelect()->placeholder('finance.add-taxes')->name('taxes')->options(
						Tax::getTaxesOptions()
					)->value($taxes->pluck('id'))
					->class('w-60 mb-0')
					->run('calculateTotals'),

					_FlexEnd(
						_TaxesInfoLink()->class('left-0 top-1'),
						_Rows(
							$taxes->map(
								fn($tax) => _Currency($amount * $tax->rate)
							)
						)->class('w-32 item-taxes font-semibold text-level1 text-right')
					)->class('relative'),
				)->class('mb-4'),
			)->class('pr-0'),
            _Rows(
                $this->deleteInvoiceDetail()
				    ->class('text-xl text-gray-300')
				    ->run('calculateTotals'),
            )->class('pt-2'),
		];
	}

	protected function deleteInvoiceDetail()
	{
		return $this->model->id ?

			_DeleteLink()->byKey($this->model) :

			_Link()->icon('icon-trash')->emitDirect('deleted');
	}

	public function rules()
	{
		return [
			'quantity_chd' => 'required',
			'price_chd' => 'required',
			'name_chd' => 'sometimes|required',
			'gl_account_id' => 'sometimes|required',
		];
	}
}
