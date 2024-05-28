<?php

namespace Condoedge\Finance\Kompo;

use App\Models\Condo\Unit;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Invoice;
use App\View\Modal;

class AccountsReceivablesSetAmountForm extends Modal
{
	protected $prefix = Invoice::PREFIX_INVOICE;
	protected $formType = Invoice::TYPE_PAYMENT;
	protected $description = 'finance.past-due-receivables';

	protected $_Title = 'finance.add-due-amount';
	protected $_Icon = 'cash';

	public $class = 'max-w-2xl overflow-y-auto mini-scroll';
	public $style = 'max-height: 95vh';

	protected $accountId;

	public function created()
	{
		$this->accountId = GlAccount::usableReceivables()->value('id'); //Yes should go to recevables even if payment
	}

	public function handle()
	{
		$unit = Unit::findOrFail(request('unit_id'));

		$invoice = Invoice::createPastInvoice($unit, carbon(currentUnion()->balance_date)->addDays(-1), request('invoice_number'), $this->formType);

        $invoice->createRegularInvoiceDetail(
        	$this->accountId,
        	request('amount'),
        	request('description'),
        );

        $invoice->journalEntriesAsContribution($this->accountId);
        $invoice->markApproved();
	}

	public function body()
	{
		return _Rows(
			_Input('finance.invoice-number')->name('invoice_number')
				->default(Invoice::getInvoiceIncrement(null, $this->prefix)),
			_Select('Unit')->name('unit_id')
				->options(
					currentUnionUnitsOptions()
				),
			_InputNumber('Amount')->name('amount'),
			_Input('Description')->name('description')->default(__('finance.balance-transfer').': '.__($this->description)),
			_SubmitButton()->closeModal()->refresh('initial-amounts-receivables'),
		);
	}

	public function rules()
	{
		return [
			'unit_id' => 'required',
			'amount' => 'required',
		];
	}
}
