s<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Bill;
use Condoedge\Finance\Models\BillDetail;
use App\Models\Supplier\Supplier;
use App\View\Modal;
use App\View\Supplier\SupplierFormAddNew;

class AccountsPayablesSetAmountForm extends Modal
{
	protected $_Title = 'finance.add-due-amount';
	protected $_Icon = 'cash';
	protected $description = 'finance.past-due-payables';

	public $class = 'max-w-2xl overflow-y-auto mini-scroll';
	public $style = 'max-height: 95vh';

	protected $accountId;	
	protected $prefix = Bill::PREFIX_BILL;
	protected $formType = Bill::TYPE_PAYMENT;

	public function created()
	{
		$this->accountId = GlAccount::usablePayables()->value('id');
	}

	public function handle()
	{
		$autoDate = carbon(currentUnion()->balance_date)->addDays(-1);

		$bill = new Bill();

		$bill->checkUniqueNumber();

		$bill->union_id = currentUnion()->id;
		$bill->supplier_id = request('supplier_id');
		$bill->bill_number = request('bill_number');
		$bill->billed_at = $autoDate;
		$bill->due_at = $autoDate;
		$bill->type = $this->formType;
		$bill->save();

		$billDetail = new BillDetail();
		$billDetail->name = request('description');
		$billDetail->account_id = $this->accountId;
		$billDetail->quantity = 1;
		$billDetail->price = request('amount');
		$bill->billDetails()->save($billDetail);

		$bill->createJournalEntries($this->accountId);
		$bill->markAccepted();
	}

	public function body()
	{
		return _Rows(
			_Input('finance.bill-number')->name('bill_number')
				->default(Bill::getBillIncrement(null, $this->prefix)),
			_SelectUpdatable('Supplier')->name('supplier_id')->placeholder('finance.billed-by')
				->options(
					Supplier::addedByTeam()->orderBy('name')->get()->mapWithKeys(fn($supplier) => $supplier->getOption())
				)
				->addsRelatedOption(SupplierFormAddNew::class)
				->addLabel('finance.create-new-supplier', 'icon-plus', 'text-xs'),
			/*_Input('finance.new-item-name')->name('name'),
			_Select('finance.associated-account')->name('account_id')
				->options(
					currentUnion()->accounts()->expense()->get()
						->mapWithKeys(fn($account) => $account->getOption())
				),
			_Date('due-date')->name('due_at'),*/
			_InputNumber('finance.amount-due')->name('amount'),
			_Input('Description')->name('description')->default(__('finance.balance-transfer').': '.__($this->description)),
			_SubmitButton()->closeModal()->refresh('initial-amounts-payables'),
		);
	}

	public function rules()
	{
		return [
			'supplier_id' => 'required',
			//'account_id' => 'required',
			//'due_at' => 'required|date_format:Y-m-d|before:'.currentUnion()->balance_date,
			'amount' => 'nullable|numeric',
		];
	}
}
