<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\ChargeDetail;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Models\Customer;
use Kompo\Form;

class InvoiceForm extends Form
{
	const ID = 'invoice-form';
	public $id = self::ID;

	use \Condoedge\Finance\Kompo\MorphManyChargeablesSelect;

	public $model = InvoiceModel::class;

	protected $customerTypePanelId = 'customer-type-panel';

	protected $team;

	public function created()
	{
		$this->team = currentTeam();
	}

	public function response()
	{
		return redirect()->route('finance.invoice-page', ['id' => $this->model->id ]);
	}

	public function render()
	{
		return [
			_FlexBetween(
				_Breadcrumbs(
	                _Link('finance-all-receivables')->href('finance.invoices-table'),
	                _Html('finance-edit'),
	            ),
				_FlexEnd4(
					$this->model->id ? _DeleteLink('finance-delete')->outlined()->byKey($this->model)->redirect('invoices.table') : null,
					_SubmitButton('finance-save'),
				)
			)->class('mb-6'),

            _CardWhiteP4(
				_Columns(
					_Select('finance-invoiced-to')->name('customer_id')->class('!mb-0')
						->options(Customer::forTeam($this->team?->id)->pluck('name', 'id')),

					_Button()->class('mb-2')->icon('plus')->selfGet('getCustomerModal')->inModal(),
				)->class('items-end mb-2'),
				_Columns(
					_DateTime('finance-invoice-date')->name('invoiced_at')->default(date('Y-m-d H:i')),
					_Date('finance-due-date')->name('due_at')->default(date('Y-m-d')),
					_Html(),
				)
			)->class('bg-white rounded-2xl shadow-lg'),

			// _TitleMini($this->labelElements)->class('uppercase mb-2'),
			// _MultiForm()->noLabel()->name('chargeDetails')
			// 	->formClass(ChargeDetailForm::class, [
			// 		'team_id' => $this->team->id,
			// 		'default_accounts' => 'usableRevenue',
			// 	])
			// 	->asTable([
			// 		__('finance-product-service'),
			// 		'',
			// 		_FlexBetween(
			// 			_Flex(
			// 				_Th('finance-quantity')->class('w-28'),
			// 				_Th('finance-price'),
			// 			)->class('space-x-4'),
			// 			_Th('finance-total')->class('text-right'),
			// 		)->class('text-sm font-medium'),
			// 	])->addLabel(
			// 		$this->getChargeablesSelect(),
			// 	)
			// 	->class('mb-6 bg-white rounded-2xl')
			// 	->id('finance-items'),

            //     _Columns(
			// 	_Rows(
			// 		_TitleMini('finance-invoice-notes')->class('mb-2'),
			// 		_CardWhiteP4(
			// 			_Textarea('finance-notes')->name('notes'),
			// 			_TagsMultiSelect(),
			// 			_MultiFile('finance-files')->name('files')
			// 				->extraAttributes([
			// 					'team_id' => $this->team->id,
			// 				])
			// 		)->class('p-6 bg-white rounded-2xl')
			// 	),
			// 	_Rows(
			// 		_TitleMini('finance-invoice-total')->class('mb-2'),
			// 		_CardWhiteP4(
			// 			_TotalCurrencyCols(__('finance-subtotal'), 'finance-subtotal', $this->model->amount, false),
			// 			_Rows(
			// 				$this->team->taxes->map(
			// 					fn($tax) => _TotalCurrencyCols($tax->name, 'finance-taxes-'.$tax->id, $this->model->getAmountForTax($tax->id))
			// 									 ->class('tax-summary')->attr(['data-id' => $tax->id])
			// 				)
			// 			),
			// 			_TotalCurrencyCols(__('finance-total'), 'finance-total', $this->model->total_amount)->class('!font-bold text-xl'),
			// 			_TaxesInfoLink()->class('left-4 bottom-6'),
			// 		)->class('relative p-6 bg-white rounded-2xl'),
			// 		_FlexEnd(
			// 			_SubmitButton('finance-save'),
			// 		),
			// 	)
			// )
		];
	}

	public function js()
	{
		return financeScriptFile();
	}

	public function getCustomerModal()
	{
		return new CustomerForm(null, [
			'refresh_id' => self::ID,
		]);
	}

	public function getTaxesInfoModal()
	{
		return new TaxesInfoModal();
	}

	public function rules()
	{
		return [
			'team_id' => 'required',
			'person_id' => 'required',
			'due_at' => 'required',
			'invoiced_at' => 'required',
		];
	}
}
