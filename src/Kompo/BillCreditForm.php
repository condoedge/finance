<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Bill;

class BillCreditForm extends BillForm
{
	protected $formType = Bill::TYPE_REIMBURSMENT;
	public $prefix = Bill::PREFIX_CREDITBILL;

	protected $labelDetails = 'finance.credit-note-details';
	protected $labelNumber = 'finance.credit-note-number';
	protected $labelElements = 'finance.credit-note-items';

	public function creditBillBeforeSave()
	{
		$this->model->status = $this->model->status ?: Bill::STATUS_PAYMENT_APPROVED;
		$this->model->approved_by = auth()->id();
	}
}
