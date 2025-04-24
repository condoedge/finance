<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\GlAccount;
use App\Models\Finance\ChargeDetail;
use App\Models\Finance\Tax;
use Condoedge\Finance\Facades\InvoiceDetailModel;
use Condoedge\Finance\Models\Account;
use Kompo\Form;

class InvoiceDetailForm extends Form
{
	public $model = InvoiceDetailModel::class;
	protected $teamId;

	public function created()
	{
		$this->teamId = $this->store('team_id');
	}

	public function render()
	{
		return [
			_Rows(
				_Input()->placeholder('finance.new-item-name')->name('name'),
			)->class('pl-4')->style('width: 15em'),

			_Input()->placeholder('finance.item-description')->name('description')->style('width: 10em'),

			_Rows(
				// $this->model->getChargeableHiddenEls($this->chargeable),
				_FlexBetween(
					_Flex(
						_Input()->type('number')
							->name('quantity')
							->default(1)
							->class('w-28 mb-0')
							->run('calculateTotals'),

						_Input()->type('number')
							->name('unit_price')
							->class('w-28 mb-0')
							->run('calculateTotals'),

						_Select()->placeholder('account')
							->class('w-36 mb-0')
							->name('revenue_account_id')
							->options(Account::pluck('name', 'id')->toArray()),
					)->class('space-x-4'),

					_FinanceCurrency(0)
						->class('item-total w-32 text-lg font-semibold text-level1 text-right')
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
			'quantity' => 'required',
			'unit_price' => 'required',
			'name' => 'sometimes|required',
			'revenue_account_id' => 'required|exists:fin_accounts,id',
		];
	}
}
