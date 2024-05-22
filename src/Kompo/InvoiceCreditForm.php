<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Models\Invoice;

class InvoiceCreditForm extends InvoiceForm
{
	protected $formType = Invoice::TYPE_REIMBURSMENT;
	public $prefix = Invoice::PREFIX_CREDITNOTE;
	
	protected $labelDetails = 'finance.credit-note-details';
	protected $labelNumber = 'finance.credit-note-number';
	protected $labelElements = 'finance.credit-note-items';
}
