<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Payments\RefundCreditDto;
use Condoedge\Finance\Models\PaymentMethod;

/**
 * Hand money back against a credit note. Distinct from applying it to an invoice:
 * here money actually leaves, so the method is required.
 *
 * Field order mirrors PaymentForm on purpose.
 */
class RefundCreditModal extends Modal
{
    protected $_Title = 'finance-refund-to-customer';

    public $model = InvoiceModel::class;

    public function handle()
    {
        PaymentService::refundCredit(new RefundCreditDto([
            'credit_id' => $this->model->id,
            'amount' => request('amount'),
            'payment_date' => request('payment_date'),
            'payment_method_id' => request('payment_method_id'),
        ]));
    }

    public function body()
    {
        return [
            _CardLevel5(
                _FinanceCurrency($this->model->abs_invoice_due_amount)->class('font-bold text-3xl'),
                _Html(__('finance-with-values-refunding-credit-note', [
                    'reference' => $this->model->invoice_reference,
                ]))->class('text-lg font-semibold'),
            )->p4()->alignEnd(),

            _Date('finance-payment-date')->name('payment_date')->default(now()),

            _Select('finance-payment-method')
                ->name('payment_method_id')
                ->options(PaymentMethod::pluck('name', 'id')->toArray()),

            _InputDollar('finance-amount')->name('amount')
                ->default($this->model->abs_invoice_due_amount->toFloat()),

            _ErrorField()->name('amount', false)->noInputWrapper()->class('!my-0'),
            _ErrorField()->name('credit_id', false)->noInputWrapper()->class('!my-0'),

            _FlexEnd(
                _SubmitButton('finance-record-refund')
                    ->refresh('invoice-page')->closeModal(),
            ),
        ];
    }

    public function rules()
    {
        return [
            'payment_date' => 'required|date',
            'payment_method_id' => 'required|exists:fin_payment_methods,id',
            'amount' => 'required|numeric|gt:0',
        ];
    }
}
