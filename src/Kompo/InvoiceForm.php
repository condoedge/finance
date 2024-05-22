<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\ChargeDetail;
use Condoedge\Finance\Models\Invoice;
use Kompo\Form;

class InvoiceForm extends Form
{
	use \Condoedge\Finance\Kompo\MorphManyChargeablesSelect;

	public $model = Invoice::class;
	protected $formType = Invoice::TYPE_PAYMENT;
	public $prefix = Invoice::PREFIX_INVOICE;

	protected $customerTypePanelId = 'customer-type-panel';

	protected $union;
	protected $minDate;
	protected $realModification;

	protected $labelDetails = 'finance.invoice-details';
	protected $labelNumber = 'finance.invoice-number';
	protected $labelElements = 'finance.invoice-items';

	public function created()
	{
		$this->union = currentUnion();
	}

	public function authorizeBoot()
	{
		return $this->model->formOpenable();
	}

	public function beforeFill()
	{
		$this->minDate = $this->model->invoiced_at ? min($this->model->invoiced_at, request('invoiced_at')) : request('invoiced_at');

		$this->realModification = ($this->model->customer_id != request('customer_id')) ||
							$this->model->dateModified('invoiced_at') ||
							$this->model->detailsModified();
	}

	public function beforeSave()
	{
        $this->model->checkUniqueNumber();

		if ($this->realModification) {
			$this->union->checkIfDateAcceptable($this->minDate);
		}

		$this->model->union_id = request('union_id');

		$this->model->type = $this->formType;
	}

	public function completed()
	{
		if ($this->realModification) {
			if ($mainTransaction = $this->model->mainTransaction) {
				$mainTransaction->checkAndDelete();
			}

            $this->model->createJournalEntries(); //Invoice are approved first
            $this->model->createInvoiceBacklogEntries();
		}
	}

	public function response()
	{
		return redirect()->route('invoice.page', ['id' => $this->model->id ]);
	}

	public function render()
	{
		if (!$this->model->chargeDetails()->count()) {
			$chargeDetail = new ChargeDetail();
			$chargeDetail->invoice_id = $this->model->id;
			$this->model->setRelation('chargeDetails', collect([$chargeDetail]));
		}

		return [
			_FlexBetween(
				_Breadcrumbs(
	                _Link('finance.all-receivables')->href('invoices.table'),
	                _Html('general.edit'),
	            ),
				_FlexEnd4(
					$this->model->id ? _DeleteLink('Delete')->outlined()->byKey($this->model)->redirect('invoices.table') : null,
					_SubmitButton('general.save'),
				)
			)->class('mb-6'),

			_DateLockErrorField(),

            _TitleMini($this->labelDetails)->class('mb-2'),

            $this->sectionBox(
				_Columns(
					_UnionSelect('finance.receiving-union')
						->readonly()
						->default($this->union->id),
					_CustomerSelect('finance.invoiced-unit'),
					_Input($this->labelNumber)->name('invoice_number')
						->default(Invoice::getInvoiceIncrement($this->union->id, $this->prefix)),
				),
				_Columns(
					_DateTime('finance.invoice-date')->name('invoiced_at')->default(date('Y-m-d H:i')),
					_Date('finance.due-date')->name('due_at')->default(date('Y-m-d')),
					_Html(),
				)
			)->class('bg-white rounded-2xl shadow-lg'),

			_TitleMini($this->labelElements)->class('uppercase mb-2'),
			_MultiForm()->noLabel()->name('chargeDetails')
				->formClass(ChargeDetailForm::class, [
					'union_id' => $this->union->id,
					'default_accounts' => 'usableRevenue',
				])
				->asTable([
					__('finance.product-service'),
					'',
					_FlexBetween(
						_Flex(
							_Th('finance.quantity')->class('w-28'),
							_Th('finance.price'),
						)->class('space-x-4'),
						_Th('finance.total')->class('text-right'),
					)->class('text-level2 text-sm font-medium border-b'),
				])->addLabel(
					$this->getChargeablesSelect(),
				)
				->class('mb-6 bg-white rounded-2xl')
				->id('finance-items'),

                _Columns(
				_Rows(
					_TitleMini('finance.invoice-notes')->class('mb-2'),
					$this->sectionBox(
						_Textarea('general.notes')->name('notes'),
						_TagsMultiSelect(),
						_MultiFile('file.attachments')->name('files')
							->extraAttributes([
								'team_id' => $this->union->team_id,
								'union_id' => $this->union->id,
							])
					)->class('p-6 bg-white rounded-2xl')
				),
				_Rows(
					_TitleMini('finance.invoice-total')->class('mb-2'),
					$this->sectionBox(
						_TotalCurrencyCols(__('finance.subtotal'), 'finance-subtotal', $this->model->amount, false),
						_Rows(
							$this->union->team->taxes->map(
								fn($tax) => _TotalCurrencyCols($tax->name, 'finance-taxes-'.$tax->id, $this->model->getAmountForTax($tax->id))
												 ->class('tax-summary')->attr(['data-id' => $tax->id])
							)
						),
						_TotalCurrencyCols(__('Total'), 'finance-total', $this->model->total_amount)->class('!font-bold text-xl'),
						_TaxesInfoLink()->class('left-4 bottom-6'),
					)->class('relative p-6 bg-white rounded-2xl'),
					_FlexEnd(
						_SubmitButton('general.save'),
					),
				)
			)
		];
	}

	protected function sectionBox()
	{
		return _Rows(...func_get_args())->class('dashboard-card pt-6 px-8 pb-4 mb-6');
	}

	public function getTaxesInfoModal()
	{
		return new TaxesInfoModal();
	}

	public function js()
	{
		return file_get_contents(resource_path('views/scripts/finance.js'));
	}

	public function rules()
	{
		return [
			'union_id' => 'required',
			'customer_id' => 'required',
			'due_at' => 'required',
			'invoiced_at' => 'required',
		];
	}
}
