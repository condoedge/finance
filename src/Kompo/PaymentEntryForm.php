<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Finance\Bill;
use App\Models\Finance\Entry;
use App\Models\Finance\GlAccount;
use App\Models\Finance\Invoice;
use Condoedge\Utils\Kompo\Common\Modal;

class PaymentEntryForm extends ModalScroll
{
	protected $modelType;

	protected $_Title = 'finance.record-payment';
	public $_Icon = 'clipboard-text';

	protected $hasInvoiceCredit = false;
	protected $hasBillCredit = false;

	protected $panelId = 'payment-type-panel';
	protected $submitInfoPanelId = 'submit-info-panel';

	public function created()
	{
		$this->modelType = $this->prop('type');

		if (!in_array($this->modelType, ['invoice', 'bill'])) {
			abort(403);
		}

		$model = 'App\\Models\\Finance\\'.ucfirst($this->modelType);

		$this->model($model::find($this->prop('id')));

		$this->hasInvoiceCredit = ($this->modelType == 'invoice') && ($this->model->customer_type == 'unit') && (
			($this->getAcompteValue() > 0) || $this->getInvoiceCreditNotes()->count()
		);

		$this->hasBillCredit = ($this->modelType == 'bill') && $this->getBillCreditNotes()->count();
	}

    public function headerButtons()
    {
        return;
    }

	protected function getAcompteValue()
	{
		return $this->model->customer->acompteValue();
	}

	protected function getInvoiceCreditNotes()
	{
		return $this->model->customer->creditNotes()->open()->get();
	}

	protected function getBillCreditNotes()
	{
		return $this->model->supplier->creditNotes()->open()->get();
	}

	public function handle()
	{
		if (!request('amount')) {
			abort(403, __('finance.cannot-enter-payment-with-zero'));
		}

		$this->model->team->checkIfDateAcceptable(request('transacted_at'));

		$this->model->createPayment(
            request('gl_account_id'),
            request('transacted_at'),
            request('amount'),
            request('payment_method'),
            request('description'),
            request('write_off'),
		);

		return redirect()->route(($this->modelType == 'invoice') ? 'finance.invoice-page' : 'finance.bill-page', [
            'id' => $this->model->id,
        ]);
	}

	public function handleInvoiceCreditNotePayment($invoiceId)
	{
		$creditNote = Invoice::find($invoiceId);

		$this->model->union->checkIfDateAcceptable(date('Y-m-d'));

		$this->model->useCreditNoteAsPayment($creditNote, date('Y-m-d'));

		return redirect()->route('invoices.stage', [
            'id' => $this->model->id,
        ]);
	}

	public function handleBillCreditNotePayment($billId)
	{
		$creditNote = Bill::find($billId);

		$this->model->union->checkIfDateAcceptable(date('Y-m-d'));

		$this->model->useCreditNoteAsPayment($creditNote, date('Y-m-d'));

		return redirect()->route('bills.stage', [
            'id' => $this->model->id,
        ]);
	}

	public function body()
	{
		return _Rows(
			_Card(
				_Rows(
					_Currency($this->model->due_amount)->class('text-2xl font-bold'),
					_Html('finance-remaining-amount-to-be-paid')->class('opacity-60'),
				)->class('text-right')
			)->class('bg-level4 p-4 !mb-2'),
			_Html('finance-how-will-you-pay')->class('text-xl font-semibold'),
			_DateLockErrorField(),
			_Panel(

				$this->hasInvoiceCredit ?

					$this->invoicePaymentTypeButtons() :

					( $this->hasBillCredit ?

							$this->billPaymentTypeButtons() :

							$this->getRegularPaymentForm()
					)

			)->id($this->panelId)
		)->class('space-y-4');
	}

	public function invoicePaymentTypeButtons()
	{
		return _Columns(
			_HugeButton('finance-regular-payment', 'money')->selfGet('getRegularPaymentForm')->inPanel($this->panelId),
			$this->getAcompteValue() ?
				_HugeButton('finance-advance-payments', 'money-time')->selfGet('getAdvancePaymentForm')->inPanel($this->panelId) : null,
			$this->getInvoiceCreditNotes()->count() ?
				_HugeButton('finance-credit-notes', 'card-add')->selfGet('getInvoiceCreditNotesForm')->inPanel($this->panelId) : null,
		);
	}

	public function billPaymentTypeButtons()
	{
		return _Columns(
			_HugeButton('finance-regular-payment', 'money')->selfGet('getRegularPaymentForm')->inPanel($this->panelId),
			_HugeButton('finance-credit-notes', 'card-add')->selfGet('getBillCreditNotesForm')->inPanel($this->panelId),
		);
	}

	public function getRegularPaymentForm()
	{
		return _Rows(
			_TitleMini('finance-regular-payment')->class('mb-4'),

			_Date('finance-payment-date')->name('transacted_at', false)->default(date('Y-m-d'))->class('mb-2'),

			_Input('finance-amount')->name('amount', false)->type('number')->step(0.01)
				->default(abs($this->model->due_amount))
				->selfGet('getSubmitInfoPanel')->inPanel($this->submitInfoPanelId)
                ->class('mb-2'),

			GlAccount::cashAccountsSelect(false)->class('mb-2'),

			Entry::paymentMethodsSelect(false)->class('mb-2'),

			_Textarea('finance-description')->name('description', false),

			_Panel(
				$this->getSubmitInfoPanel($this->model->due_amount)
			)->id($this->submitInfoPanelId)
		);
	}

	public function getSubmitInfoPanel($amount)
	{
		return static::getWriteOffElements('finance.record-payment', $this->model->due_amount, $amount);
	}

	public static function getWriteOffElements($label, $dueAmount, $amount)
	{
		$difference = $amount - $dueAmount;
		$roundedDiff = round(abs($difference), 2);

		$text = $difference < 0 ? __('finance.remaining') : __('finance.in-excess');

		$submitBtn = _SubmitButton($label);

		if ($roundedDiff > 0 && $roundedDiff < 1) {
			return _Rows(
				_Html(__('finance.there-is-a').' '.$roundedDiff.'$ '.$text.' '.__('finance.could-be-write-off')),
				_Checkbox('finance.yes-write-off')->name('write_off'),
				$submitBtn
			)->class('space-y-4 card-gray-100 p-4');
		}

		return $submitBtn;
	}

	public function getAdvancePaymentForm()
	{
		return new PaymentAcompteApplyForm($this->model->id);
	}

	public function getInvoiceCreditNotesForm()
	{
		return _Rows(
			$this->getInvoiceCreditNotes()->map(
				fn($invoice) => _FlexBetween(
					_Rows(
						_Html($invoice->number_display)->class('text-xl font-bold'),
						_Html($invoice->customer_label),
					),
					_Rows(
						_Currency(abs($invoice->due_amount))->class('text-xl font-black'),
						_Html($invoice->invoiced_at->translatedFormat('d M Y')),
					)->class('text-right')
				)->class('p-4 card-gray-100 cursor-pointer hover:shadow')
				->selfPost('handleInvoiceCreditNotePayment', ['invoice_id' => $invoice->id])
				->redirect()
			)
		);
	}

	public function getBillCreditNotesForm()
	{
		return _Rows(
			$this->getBillCreditNotes()->map(
				fn($bill) => _FlexBetween(
					_Rows(
						_Html($bill->number_display)->class('text-xl font-bold'),
						_Html($bill->supplier->display),
					),
					_Rows(
						_Currency(abs($bill->due_amount))->class('text-xl font-black'),
						_Html($bill->billed_at->translatedFormat('d M Y')),
					)->class('text-right')
				)->class('p-4 card-gray-100 cursor-pointer hover:shadow')
				->selfPost('handleBillCreditNotePayment', ['bill_id' => $bill->id])
				->redirect()
			)
		);
	}

	public function rules()
	{
		return [
			'transacted_at' => 'required|date',
			'gl_account_id' => 'required',
			'amount' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
			'payment_method' => 'required',
		];
	}
}
