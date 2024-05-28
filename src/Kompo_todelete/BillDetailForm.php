<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Crm\Union;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\BillDetail;
use Condoedge\Finance\Models\BillItem;
use Condoedge\Finance\Models\Tax;
use Kompo\Form;

class BillDetailForm extends Form
{
	public $model = BillDetail::class;

	public $class = 'align-top';

	protected $billItemId;
	protected $billItem;

	protected $unionId;
	protected $union;

	public function created()
	{
		$this->billItemId = $this->store('bill_item_id');
		$this->billItem = BillItem::find($this->billItemId);

		$this->unionId = $this->store('union_id');
		$this->union = Union::find($this->unionId);
	}

	public function beforeSave()
	{
		if (!($billItem = BillItem::find(request('bill_item_id')) )) {
			$billItem = new BillItem();
			$billItem->setUnionId($this->unionId);
		}

		$billItem->name = request('name');
		$billItem->price = request('price');
		$billItem->account_id = request('account_id');
		$billItem->save();

		$billItem->taxes()->sync(request('taxes'));

		$this->model->bill_item_id = $billItem->id;

		\Cache::forever('bill-latest-taxes-'.auth()->id(), request('taxes'));
	}

	public function render()
	{
		$item = $this->billItemId ? $this->billItem : $this->model;
		$amount = $this->billItemId ? $this->billItem->price : $this->model->amount;
		$taxes = ($this->billItemId || $this->model->id) ? $item?->taxes->pluck('id') : \Cache::get('bill-latest-taxes-'.auth()->id());

		$usableAccounts = GlAccount::usableExpense($this->union)->get();

		return [
			_Rows(
				_Input()->placeholder('finance.new-item-name')->name('name')
					->default($item?->name),
				_Select()->placeholder('finance.associated-account')->name('account_id')
					->options(
						$usableAccounts->mapWithKeys(fn($account) => $account->getOption())
					)
					->default($item?->account_id) //No default account when empty because it doesn't make sense for bills
					->class('mb-0'),
			)->class('pl-4 w-72'),

			_Input()->placeholder('finance.item-description')->name('description')->style('width: 20em'),

            _Rows(
				_Hidden()->name('bill_item_id', false)->value($this->billItemId ?: $this->model->bill_item_id),
				_FlexBetween(
					_Flex(
						_Input()->type('number')
							->name('quantity')
							->default(1)
							->class('w-28 mb-0')
							->run('calculateTotals'),

						_Input()->type('number')
							->name('price')
							->class('w-28 mb-0')
							->run('calculateTotals')
							->value($item?->price),
					)->class('space-x-4'),

					_Currency($amount)
						->class('item-total w-32 text-lg font-semibold text-level1 text-right')
				)->class('mb-4'),

				_FlexBetween(
					_MultiSelect()->placeholder('finance.add-taxes')->name('taxes')->options(
						Tax::getTaxesOptions()
					)->value($taxes)
					->class('mb-0')
                    ->style('width: 15em')
					->run('calculateTotals'),

					_FlexEnd(
						_TaxesInfoLink()->class('left-0 top-1'),
						_Rows(
							$item?->taxes->map(
								fn($tax) => _Currency($amount * $tax->rate)
							)
						)->class('w-32 item-taxes font-semibold text-level1 text-right'),

					)->class('relative'),
				)->class('mb-4'),
			)->class('pr-0'),
            _Rows(
                $this->deleteBillDetail()
                    ->class('text-xl text-gray-500')
                    ->run('calculateTotals'),
            )->class('pt-2'),
		];
	}

	protected function deleteBillDetail()
	{
		return $this->model->id ?

			_DeleteLink()->byKey($this->model) :

			_Link()->icon('icon-trash')->emitDirect('deleted');
	}

	public function rules()
	{
		return [
			'quantity' => 'required',
			'price' => 'required',
			'name' => 'sometimes|required',
			'account_id' => 'sometimes|required',
		];
	}
}
