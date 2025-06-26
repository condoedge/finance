<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentForInvoiceDto;
use Condoedge\Finance\Models\MorphablesEnum;
use Condoedge\Finance\Services\Payment\PaymentServiceInterface;

class PaymentForm extends Modal
{
    protected $_Title = 'finance-record-payment';

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

    public function handle(PaymentServiceInterface $paymentService)
    {
        $applyInformation = [
            'payment_date' => request('payment_date'),
            'amount' => request('amount') * request('type'),
        ];

        if ($this->invoiceId) {
            $paymentService->createPaymentAndApplyToInvoice(new CreateCustomerPaymentForInvoiceDto([
                'invoice_id' => $this->invoiceId,
                ...$applyInformation,
            ]));
        } else {
            $payment = $paymentService->createPayment(new CreateCustomerPaymentForInvoiceDto([
                'customer_id' => $this->customerId,
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
        $paymentType = $this->invoice?->invoice_type_id->signMultiplier() < 0 ? -1 : 1;

        return [
            !$this->invoice ? null : _CardLevel5(
                _FinanceCurrency($this->invoice->abs_invoice_due_amount)->class('font-bold text-3xl'),
                _Html(__('finance-with-values-paying-invoice', [
                    'invoice_reference' => $this->invoice->invoice_reference,
                ]))->class('text-lg font-semibold'),
            )->p4()->alignEnd(),

            ($this->invoiceId || !$this->goToApplyModelAfter) ? null :
                _CardGray100P4(_Html('finance-going-to-apply-page-after-this-payment')),

            $this->invoice ? null : teamCustomersSelect(customerId: $this->customerId),

            _Date('finance-payment-date')->name('payment_date')->default(now())
                ->placeholder('finance-payment-date'),

            $this->invoice ? _Hidden()->name('type')->default($paymentType) : _ButtonGroup('finance-select-type')->name('type')
                ->when($this->invoice, fn($e) => $e->default($paymentType))
                ->options([
                    1 => __('finance-from-customer'),
                    -1 => __('finance-to-customer'),
                ]),

            _InputDollar('finance-amount')->name('amount')->default($this->invoice?->abs_invoice_due_amount->toFloat())
                ->placeholder('finance-amount'),

            _ErrorField()->name('amount_applied', false)->noInputWrapper()->class('!my-0'),

            _FlexEnd(
                _SubmitButton('finance-save')->refresh($this->refreshId)->closeModal()
                    ->when($this->goToApplyModelAfter, fn($e) => $e->inModal()),
            )
        ];
    }

    public function rules()
    {
    	return [
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:1,-1',
    	];
    }
}
