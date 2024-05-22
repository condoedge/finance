<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Condo\Unit;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Acompte;
use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\Invoice;
use App\View\Modal;

class PaymentPrepayInvoiceModal extends Modal
{
	protected $_Title = 'finance.record-payment';
	protected $_Icon = 'cash';

	public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height:95vh';

    protected $panelId = 'units-invoices-mini-table';

	public function handle()
	{
		if (!request('amount')) {
			abort(403, __('finance.cant-enter-zero-payment'));
		}

        if (!currentUnion()->acceptsFinanceChange(request('transacted_at'))) {
            abort(403, balanceLockedMessage(currentUnion()->latestBalanceDate()));
        }

		$remainingAmount = request('amount');
		$invoicesId = is_string(request('invoices_id')) ? explode(',', request('invoices_id')) : request('invoices_id');

		//Try first the chosen invoices
		$remainingAmount = $this->applyPaymentsTo($remainingAmount, Invoice::whereIn('id', $invoicesId ?: [])->get());

		//Then the unpaid invoices
		$remainingAmount = $this->applyPaymentsTo($remainingAmount, Invoice::getDueInvoices(request('customer')));

		//Add to acompte if there's an amount left
		if ($remainingAmount >= 0.01) {
			Acompte::createForUnit(
				Unit::findOrFail(request('customer')),
				request('account_id'),
	            request('transacted_at'),
	            $remainingAmount,
	            request('payment_method'),
	            request('description'),
	        );
		}
	}

	protected function applyPaymentsTo($remainingAmount, $invoices)
	{
		$nextInvoice = $invoices->first();

		while (($remainingAmount >= 0.01) && $nextInvoice) {

			$amount = min($remainingAmount, $nextInvoice->due_amount);

			if ($amount) {
				$nextInvoice->createPayment(
		            request('account_id'),
		            request('transacted_at'),
		            $amount,
		            request('payment_method'),
		            request('description'),
				);
			}

			$remainingAmount = $remainingAmount - $amount;
			$invoices->shift();
			$nextInvoice = $invoices->first();
		}

		return $remainingAmount;
	}

	public function headerButtons()
	{
		return _SubmitButton('finance.record-payment')->closeModal();
	}

	public function body()
	{
		return _Columns(
			_Rows(
	            _Select('Unit')->name('customer')->options(currentUnion()->unitOptions())
	            	->getElements('getPaymentPrepayMiniInvoicesTable')->inPanel($this->panelId),

				_Date('finance.payment-date')->name('transacted_at')->default(date('Y-m-d')),

				_Input('Amount')->name('amount')->type('number')->step(0.01),

				GlAccount::cashAccountsSelect(),

				Entry::paymentMethodsSelect(),

				_Textarea('Description')->name('description'),
			),
			_Rows(
				_Panel(
					$this->getPaymentPrepayMiniInvoicesTable()
				)->id($this->panelId)
				->class('overflow-y-auto mini-scroll')
				->style('height:calc(95vh - 100px)')
			)
		);
	}

	public function rules()
	{
		return [
			'transacted_at' => 'required|date',
			'account_id' => 'required',
			'amount' => 'required|numeric|regex:/^\d+(\.\d{1,2})?$/',
			'payment_method' => 'required',
			'customer' => 'required',

		];
	}

	public function getPaymentPrepayMiniInvoicesTable($unitId = null)
	{
		if (!$unitId) {
			return _Html('finance.select-unit-to-view-invoices')->class('card-gray-100 p-4');
		}

		$dueInvoices = Invoice::getDueInvoices($unitId);

		if (!$dueInvoices->count()) {
			return _Html('finance.no-upcoming-due-invoices')->class('card-gray-100 p-4');
		}

		return _MultiButtonGroup('finance.pick-invoices-to-pay-first')->vertical()
			->name('invoices_id')->selectedClass('bg-level1 font-medium', 'hidecheck text-gray-500')
			->options(
				Invoice::getDueInvoices($unitId)->mapWithKeys(fn($invoice) => [
					$invoice->id => _FlexBetween(
			            _Rows(
			                $invoice->statusBadge()->class('text-xxs pt-0 pb-0'),
			                _Html($invoice->invoice_number)->icon('icon-check'),
			                _Html(dateStr($invoice->due_at))->class('text-gray-600'),
			            )->class('space-y-2'),
			            _Rows(
			                _Html('finance.amount-due')->class('text-xxs font-bold text-gray-600'),
			                _Currency($invoice->due_amount),
			                _Flex(
			                    _Html('/'),
			                    _Currency($invoice->total_amount),
			                )->class('space-x-2 text-gray-600'),
			            )->class('items-end'),
			        )->class('p-2 text-xs')
				])
			);
	}
}
