<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Bill;

class AccountsPayablesCreditSetAmountForm extends AccountsPayablesSetAmountForm
{
	protected $prefix = Bill::PREFIX_CREDITBILL;
	protected $formType = Bill::TYPE_REIMBURSMENT;
	protected $description = 'finance.past-due-payment-payables';
}
