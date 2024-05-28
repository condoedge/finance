<?php

namespace Condoedge\Finance\Kompo;

use App\Models\File;
use Condoedge\Finance\Models\Bill;
use Condoedge\Finance\Models\ChargeDetail;
use App\Models\Market\Supplier;
use Illuminate\Support\Carbon;
use Kompo\Form;

class BillForm extends Form
{
	use \Condoedge\Finance\Kompo\MorphManyChargeablesSelect;

	public $model = Bill::class;
	protected $formType = Bill::TYPE_PAYMENT;
	public $prefix = Bill::PREFIX_BILL;

	protected $selectedSupplierId;

	protected $union;
	protected $minDate;
	protected $realModification;

	protected $dueDatePanelId = 'due-date-panel-id';

	protected $labelDetails = 'finance.bill-details';
	protected $labelNumber = 'finance.bill-number';
	protected $labelElements = 'finance.bill-items';

	public function authorizeBoot()
	{
		return $this->model->formOpenable();
	}

	public function created()
	{
		$this->union = currentUnion();
	}

	public function beforeFill()
	{
		$this->minDate = $this->model->billed_at ? min($this->model->billed_at, request('billed_at')) : request('billed_at');

		$this->realModification = ($this->model->supplier_id != request('supplier_id')) ||
							$this->model->dateModified('billed_at') ||
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

		$this->model->due_at = request('due_at');

		$this->creditBillBeforeSave();

	}

	public function creditBillBeforeSave()
	{
		// Overridden in credit note

		$this->model->status = $this->model->status ?:
			($this->union->board_approves_bills ? Bill::STATUS_RECEIVED : Bill::STATUS_PAYMENT_APPROVED);
		$this->model->approved_by = $this->union->board_approves_bills ? null : auth()->id();
	}

	public function completed()
	{
		if ($this->realModification) {
			//Journal entries
			if ($mainTransaction = $this->model->mainTransaction) {
				$mainTransaction->checkAndDelete();
			}

			$this->model->createJournalEntries();
            $this->model->createBillBacklogEntries();
		}
	}

	public function response()
	{
		return redirect()->route('bill.page', ['id' => $this->model->id ]);
	}

	public function render()
	{
		if (!$this->model->chargeDetails()->count()) {
			$chargeDetail = new ChargeDetail();
			$chargeDetail->bill_id = $this->model->id;
			$this->model->setRelation('chargeDetails', collect([$chargeDetail]));
		}

		$suppliers = Supplier::forTeam();

		return [
			_FlexBetween(
				_Breadcrumbs(
	                $this->titleBack(),
	                _Html('general.edit'),
	            ),
				_FlexEnd4(
					$this->deleteBillButtons(),
					_SubmitButton('general.save'),
				)
			)->class('mb-6'),

			_DateLockErrorField(),

            $this->getRecurrenceFields(),

            _TitleMini($this->labelDetails)->class('mb-2'),

            $this->sectionBox(
				_Columns(
					_UnionSelect('finance.clinic')
						->readonly()
						->default($this->union->id),
					_SelectUpdatable('finance.supplier')->name('supplier_id')->placeholder('finance.billed-by')
						->options(
							$suppliers->get()->mapWithKeys(fn($supplier) => $supplier->getBasicOption())
						)->default($this->selectedSupplierId)
						->addsRelatedOption(SupplierFormAddNew::class)
						->addLabel('finance.create-new-supplier', 'icon-plus', 'text-xs')
						->getElements('getDueDate', null, true)
						->inPanel($this->dueDatePanelId),
					_Input('finance.supplier-bill-number')->name('supplier_number'),
                    _Input($this->labelNumber)->name('bill_number')->default(Bill::getBillIncrement($this->union->id, $this->prefix)),
				),
				_Columns(
					$this->getBillDateEl(),
					_Panel(
						$this->getDueDateEl()->value($this->model->due_at)
					)->id($this->dueDatePanelId),
					$this->getWorkDateEl(),
					_Html()
				)
			)->class('bg-white rounded-2xl shadow-lg'),

            _TitleMini($this->labelElements)->class('mb-2'),

            _MultiForm()->noLabel()->name('chargeDetails')
				->formClass(ChargeDetailForm::class, [
					'union_id' => $this->union->id,
					'default_accounts' => 'usableExpense',
				])
				->asTable([
					__('finance.product-service'),
                    '',
					_FlexBetween(
						_Flex(
							_Th('finance.quantity')->class('w-28'),
							_Th('finance.price')->class('w-24'),
						)->class('space-x-4'),
						_Th('finance.total')->class('text-right'),
					)->class('text-level2 text-sm border-b'),
                    '',
				])->addLabel(
					$this->getChargeablesSelect(),
				)
				->class('mb-6 dashboard-card bg-white rounded-2xl')
				->id('finance-items'),
			_Columns(
				_Rows(
					_TitleMini('finance.bill-notes')->class('mb-2'),
					$this->sectionBox(
						_Textarea('general.notes')->name('notes'),
						_TagsMultiSelect(),
						_MultiFile('file.attachments')->name('files')
							->extraAttributes([
								'team_id' => $this->union->team_id,
								'union_id' => $this->union->id,
							])
					)->class('bg-white rounded-2xl p-6')
				),
				_Rows(
					_TitleMini('finance.bill-total')->class('mb-2'),
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
					)->class('relative bg-white rounded-2xl p-6'),
					_FlexEnd(
						_SubmitButton('general.save'),
					),
				)
			)
		];
	}

	protected function titleBack()
	{
		return _Link('finance.all-payables')->href('bills.table');
	}

	protected function deleteBillButtons()
	{
		return $this->model->id ? _DeleteLink('finance.delete')->outlined()->byKey($this->model)->redirect('bills.table') : null;
	}

	protected function getBillDateEl()
	{
		return _DateTime('finance.bill-date')->name('billed_at')->default(date('Y-m-d H:i'))
			->getElements('getDueDate', null, true)
			->inPanel($this->dueDatePanelId);
	}

	protected function getWorkDateEl()
	{
		return _Date('finance.work-date')->name('worked_at')->default(date('Y-m-d H:i'));
	}

	public function getDueDate()
	{
		$billedAt = substr(request('billed_at'), 0, 10) ?: ($this->model->billed_at?->format('Y-m-d') ?: date('Y-m-d'));
		$supplierId = request('supplier_id') ?: ($this->model->supplier_id ?: $this->selectedSupplierId);

		$dueDate = $this->getDueDateEl();

		if (!($supplier = Supplier::find($supplierId)) || !$supplier->term) {
			return $dueDate->value($billedAt);
		}

		return $dueDate->value(carbon($billedAt)->addDays($supplier->term)->format('Y-m-d'));
	}

	protected function getDueDateEl()
	{
		return _Date('finance.due-date')->name('due_at', false);
	}

	protected function getRecurrenceFields()
	{
		return; // Overridden
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
			'supplier_id' => 'required',
			'union_id' => 'required',
			'due_at' => 'required',
			'billed_at' => 'required',
		];
	}
}
