<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Billing\PaymentGatewayResolver;
use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentGateway;
use Condoedge\Finance\Facades\PaymentTypeEnum;
use Condoedge\Finance\Models\Dto\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\CreateOrUpdateInvoiceDetail;
use Condoedge\Finance\Models\Dto\UpdateInvoiceDto;
use Condoedge\Utils\Kompo\Common\Form;

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

	public function handle()
	{
		$parsedDetails = collect(request()->get('invoiceDetails', []));

		$parsedDetails->transform(function ($detail) {
			$detail['id'] = $detail['multiFormKey'] ?? null;

			return $detail;
		});

		$sharedDtoData = request()->all();
		$sharedDtoData['invoiceDetails']  = $parsedDetails->toArray();

		if ($this->model->id) {
			$sharedDtoData['id'] = $this->model->id;
			InvoiceModel::updateInvoiceFromDto(new UpdateInvoiceDto($sharedDtoData));
		} else {
			InvoiceModel::createInvoiceFromDto(new CreateInvoiceDto($sharedDtoData));
		}

		return $this->response();
	}

	public function response()
	{
		return redirect()->route('invoices.show', ['id' => $this->model->id]);
	}

	public function render()
	{
		return [
			_FlexBetween(
				_Breadcrumbs(
	                _BackLink('finance-all-receivables')->href('invoices.list'),
	                _Html('finance-edit'),
	            ),
				_FlexEnd4(
					$this->model->id ? _DeleteLink('finance-delete')->outlined()->byKey($this->model)->redirect('invoices.table') : null,
					_SubmitButton('finance-save'),
				)
			)->class('mb-6'),

            _CardWhiteP4(
				$this->model->id ? null : _Select('translate.finance.invoice-type')
					->name('invoice_type_id')
					->options(InvoiceTypeEnum::optionsWithLabels()),
				_Select('translate.finance.payment-type')
					->name('payment_type_id')
					->options(PaymentTypeEnum::optionsWithLabels()),
				$this->model->id ? null : _Columns(
					new SelectCustomer(null, [
						'team_id' => $this->team?->id,
						'default_id' => $this->model->customer_id,
					]),

					_Button()->class('mb-2')->icon('plus')->selfGet('getCustomerModal')->inModal(),
				)->class('items-end mb-2'),
				_Columns(
					_DateTime('finance-invoice-date')->name('invoice_date')->default(date('Y-m-d H:i')),
					_DateTime('finance-due-date')->name('invoice_due_date')->default(date('Y-m-d')),
					_Html(),
				)
			)->class('bg-white rounded-2xl shadow-lg'),

			// _TitleMini($this->labelElements)->class('uppercase mb-2'),
			_MultiForm()->noLabel()->name('invoiceDetails')
				->formClass(InvoiceDetailForm::class, [
					'team_id' => $this->team->id,
				])
				->asTable([
					__('finance-product-service'),
					'',
					_FlexBetween(
						_Flex(
							_Th('finance-quantity')->class('w-28'),
							_Th('finance-price')->class('w-28'),
							_Th('finance-account')->class('w-36'),
						)->class('space-x-4'),
						_Th('finance-total')->class('text-right'),
					)->class('text-sm font-medium'),
				])->addLabel(
					$this->getChargeablesSelect(),
				)
				->class('mb-6 bg-white rounded-2xl')
				->id('finance-items'),

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
			'refresh_id' => 'select-customer',
		]);
	}

	public function getTaxesInfoModal()
	{
		return new TaxesInfoModal();
	}

	public function rules()
	{
		return [
			// 'invoice_due_date' => 'required',
			// 'invoice_date' => 'required',
			// 'payment_type_id' => 'required',
			// 'invoice_type_id' => 'required',
		];
	}
}
