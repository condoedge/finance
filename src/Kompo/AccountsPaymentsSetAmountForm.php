<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;

class AccountsPaymentsSetAmountForm extends AccountsReceivablesSetAmountForm
{
	protected $prefix = Invoice::PREFIX_CREDITNOTE;
	protected $formType = Invoice::TYPE_REIMBURSMENT;
	protected $description = 'finance.past-due-payment';
}
