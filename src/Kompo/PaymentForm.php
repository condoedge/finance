<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoicePaymentModel;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Dto\CreateApplyForInvoiceDto;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Entry;
use Condoedge\Finance\Models\MorphablesEnum;

class PaymentForm extends Modal
{
	protected $_Title = 'finance.record-payment';

    public $model = CustomerPayment::class;

    protected $customerId;
    protected $refreshId;

    protected $invoiceId;
    protected $invoice;

    public function created()
    {
        $this->customerId = $this->prop('customer_id'); 
        $this->refreshId = $this->prop('refresh_id');

        $this->invoiceId = $this->prop('invoice_id');

        $this->invoice = !$this->invoiceId ? null : InvoiceModel::findOrFail($this->invoiceId);
    }

    public function handle()
    {
        $applyInformation = [
            'payment_date' => request('payment_date'),
            'amount' => request('amount'),
        ];

        if ($this->invoiceId) {
            CustomerPayment::createForCustomerAndApply(new \Condoedge\Finance\Models\Dto\CreateCustomerPaymentForInvoiceDto([
                'invoice_id' => $this->invoiceId,
                ...$applyInformation,
            ]));
        } else {
            CustomerPayment::createForCustomer(new \Condoedge\Finance\Models\Dto\CreateCustomerPaymentDto([
                'customer_id' => $this->customerId,
                ...$applyInformation,
            ]));
        }
    }

	public function body()
	{
		return [
            !$this->invoice ? null : _CardGray100P4(
                _Html('paying invoice'),
                _Html($this->invoice->invoice_reference),
                _Html('amount due'),
                _FinanceCurrency($this->invoice->invoice_due_amount),
            ),

            new SelectCustomer(null, [
                'team_id' => currentTeamId(),
                'default_id' => $this->model->customer_id ?? $this->customerId,
            ]),

            _Date('finance-payment-date')->name('payment_date')->required()
                ->placeholder(__('finance-payment-date')),

            _Input('finance-amount')->name('amount')->required()->type('number')
                ->placeholder(__('finance-amount')),

            _ErrorField()->name('amount_applied', false),

            _FlexEnd(
                _SubmitButton('finance.save')->refresh($this->refreshId)->closeModal(),
            )
		];
	}

	// public function rules()
	// {
	// 	return [
    //         'customer_id' => 'required|exists:fin_customers,id',
    //         'amount' => 'required|numeric|min:0',
	// 	];
	// }
}
