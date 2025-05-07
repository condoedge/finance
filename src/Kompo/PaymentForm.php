<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\MorphablesEnum;

class PaymentForm extends Modal
{
    protected $_Title = 'translate.finance.record-payment';

    public $model = CustomerPayment::class;

    protected $customerId;
    protected $refreshId;

    protected $invoiceId;
    protected $invoice;

    protected $goToApplyModelAfter = false;

    public function created()
    {
        $this->customerId = $this->prop('customer_id');
        $this->refreshId = $this->prop('refresh_id');

        $this->invoiceId = $this->prop('invoice_id');

        $this->invoice = !$this->invoiceId ? null : InvoiceModel::findOrFail($this->invoiceId);

        $this->goToApplyModelAfter = $this->prop('go_to_apply_model_after');
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
            $payment = CustomerPayment::createForCustomer(new \Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto([
                'customer_id' => $this->customerId ?? request('customer_id'),
                ...$applyInformation,
            ]));

            if ($this->goToApplyModelAfter) {
                return new ApplyPaymentToInvoiceModal(null, [
                    'applicable_key' => MorphablesEnum::PAYMENT->value . '|' . $payment->id,
                    'refresh_id' => $this->refreshId,
                ]);
            }
        }
    }

    public function body()
    {
        return [
            !$this->invoice ? null : _CardLevel5(
                _FinanceCurrency($this->invoice->invoice_due_amount)->class('font-bold text-3xl'),
                _Html(__('translate.with-values.paying-invoice', [
                    'invoice_reference' => $this->invoice->invoice_reference,
                ]))->class('text-lg font-semibold'),
            )->p4()->alignEnd(),

            ($this->invoiceId || !$this->goToApplyModelAfter) ? null :
                _CardGray100P4(_Html('translate.going-to-apply-page-after-this-payment')),

            $this->invoice ? null : teamCustomersSelect(customerId: $this->customerId),

            _Date('finance-payment-date')->name('payment_date')->default(now())
                ->placeholder('finance-payment-date'),

            _InputDollar('finance-amount')->name('amount')->default($this->invoice?->invoice_due_amount)
                ->placeholder('finance-amount'),

            _ErrorField()->name('amount_applied', false)->noInputWrapper()->class('!my-0'),

            _FlexEnd(
                _SubmitButton('translate.finance.save')->refresh($this->refreshId)->closeModal()
                    ->when($this->goToApplyModelAfter, fn($e) => $e->inModal()),
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
