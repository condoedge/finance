<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\ChargeDetail;
use App\Models\Finance\Invoice;
use App\Models\Crm\Person;
use Kompo\Form;

class InvoiceForm extends Form
{
	use \Condoedge\Finance\Kompo\MorphManyChargeablesSelect;

	public $model = Invoice::class;
	protected $formType = Invoice::TYPE_PAYMENT;
	public $prefix = Invoice::PREFIX_INVOICE;

	protected $customerTypePanelId = 'customer-type-panel';

	protected $team;
	protected $minDate;
	protected $realModification;

	protected $labelDetails = 'finance-invoice-details';
	protected $labelNumber = 'finance-invoice-number';
	protected $labelElements = 'finance-invoice-items';

	public function created()
	{
		$this->team = currentTeam();
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
			$this->team->checkIfDateAcceptable($this->minDate);
		}

		$this->model->team_id = request('team_id');

		$this->model->type = $this->formType;
	}

	public function completed()
	{
		if ($this->realModification) {
			if ($mainTransaction = $this->model->mainTransaction) {
				$mainTransaction->checkAndDelete();

	            $this->model->journalEntriesAsInvoice(); 
	            //$this->model->createInvoiceBacklogEntries();
			}

		}
	}

	public function response()
	{
		return redirect()->route('finance.invoice-page', ['id' => $this->model->id ]);
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
	                _Link('finance-all-receivables')->href('finance.invoices-table'),
	                _Html('finance-edit'),
	            ),
				_FlexEnd4(
					$this->model->id ? _DeleteLink('finance-delete')->outlined()->byKey($this->model)->redirect('invoices.table') : null,
					_SubmitButton('finance-save'),
				)
			)->class('mb-6'),

			_DateLockErrorField(),

            _TitleMini($this->labelDetails)->class('mb-2'),

            _CardWhiteP4(
				_Columns(
					_Select('finance-receiving-team')->name('team_id')
						->options([
							$this->team->id => $this->team->team_name,
						])
						->readonly()
						->default($this->team->id),
					_Select('finance-invoiced-to')->name('person_id')
						->options(
							Person::getOptionsForTeamWithFullName($this->team->id)
						),
					_Input($this->labelNumber)->name('invoice_number')
						->default(Invoice::getInvoiceIncrement($this->team->id, $this->prefix)),
				),
				_Columns(
					_DateTime('finance-invoice-date')->name('invoiced_at')->default(date('Y-m-d H:i')),
					_Date('finance-due-date')->name('due_at')->default(date('Y-m-d')),
					_Html(),
				)
			)->class('bg-white rounded-2xl shadow-lg'),

			_TitleMini($this->labelElements)->class('uppercase mb-2'),
			_MultiForm()->noLabel()->name('chargeDetails')
				->formClass(ChargeDetailForm::class, [
					'team_id' => $this->team->id,
					'default_accounts' => 'usableRevenue',
				])
				->asTable([
					__('finance-product-service'),
					'',
					_FlexBetween(
						_Flex(
							_Th('finance-quantity')->class('w-28'),
							_Th('finance-price'),
						)->class('space-x-4'),
						_Th('finance-total')->class('text-right'),
					)->class('text-sm font-medium'),
				])->addLabel(
					$this->getChargeablesSelect(),
				)
				->class('mb-6 bg-white rounded-2xl')
				->id('finance-items'),

                _Columns(
				_Rows(
					_TitleMini('finance-invoice-notes')->class('mb-2'),
					_CardWhiteP4(
						_Textarea('finance-notes')->name('notes'),
						_TagsMultiSelect(),
						_MultiFile('finance-files')->name('files')
							->extraAttributes([
								'team_id' => $this->team->id,
							])
					)->class('p-6 bg-white rounded-2xl')
				),
				_Rows(
					_TitleMini('finance-invoice-total')->class('mb-2'),
					_CardWhiteP4(
						_TotalCurrencyCols(__('finance-subtotal'), 'finance-subtotal', $this->model->amount, false),
						_Rows(
							$this->team->taxes->map(
								fn($tax) => _TotalCurrencyCols($tax->name, 'finance-taxes-'.$tax->id, $this->model->getAmountForTax($tax->id))
												 ->class('tax-summary')->attr(['data-id' => $tax->id])
							)
						),
						_TotalCurrencyCols(__('finance-total'), 'finance-total', $this->model->total_amount)->class('!font-bold text-xl'),
						_TaxesInfoLink()->class('left-4 bottom-6'),
					)->class('relative p-6 bg-white rounded-2xl'),
					_FlexEnd(
						_SubmitButton('finance-save'),
					),
				)
			)
		];
	}

	public function js()
	{
		return financeScriptFile();
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
