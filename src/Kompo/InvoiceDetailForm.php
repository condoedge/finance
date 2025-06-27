<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceDetailModel;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\Tax;
use Kompo\Form;

class InvoiceDetailForm extends Form
{
	public $model = InvoiceDetailModel::class;
	public $class = 'align-top';
	protected $teamId;
	protected $invoiceId;
	protected $invoice;

	public function created()
	{
		$this->teamId = $this->prop('team_id');
	}

	public function render()
	{
		// If the taxes were set before and they are now disabled, we should allow them anyways
		$taxesOptions = $this->model?->invoiceTaxes()->with('tax')->get()->mapWithKeys(
			fn($it) => [$it->tax->id => $it->complete_label_html])
		->union(Tax::active()->get()->pluck('complete_label_html', 'id'));

		return [
			_Rows(
				_Input()->placeholder('finance.new-item-name')->name('name'),
			)->class('pl-4 w-72'),

			_Input()->placeholder('finance.item-description')->name('description')->style('width: 20em'),

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

						_Rows(
							_Select()->placeholder('account')
								->class('w-36 !mb-0')
								->name('revenue_segment_account_id')
								->options(SegmentValue::forLastSegment()->get()->mapWithKeys(
									fn($it) => [$it->id => $it->segment_value . ' - ' . $it->segment_description]
								)),
						),

					)->class('space-x-4'),

					_FinanceCurrency($this->model->extended_price)
						->class('item-total w-32 text-lg font-semibold text-level1 text-right')
				)->class('mb-4'),

				
				_FlexBetween(
					_MultiSelect()->placeholder('taxes')
						->class('w-60 mb-0 mt-2')
						->name('taxesIds', false)
						->default($this->model->id ? $this->model->invoiceTaxes()->pluck('tax_id') : InvoiceService::getDefaultTaxesIds($this->model->invoice))
						->options($taxesOptions)
						->run('calculateTotals'),

					_FlexEnd(
						_TaxesInfoLink()->class('left-0 top-1 ml-1'),
						_Rows(
							$this->model->invoiceTaxes()->get()->map(
								fn($it) => _FinanceCurrency($this->model->extended_price->multiply($it->tax_rate))
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
			'quantity' => 'required',
			'unit_price' => 'required',
			'name' => 'sometimes|required',
			'revenue_segment_account_id' => 'required|exists:fin_segment_values,id',
		];
	}
}
