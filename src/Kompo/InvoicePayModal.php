<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Utils\Kompo\Common\Form;
use Condoedge\Finance\Models\PaymentMethod;
use Condoedge\Finance\Models\Dto\Invoices\PayInvoiceDto;

class InvoicePayModal extends Form
{
    public $class = 'overflow-y-auto mini-scroll max-w-2xl min-w-[400px]';
    public $style = 'max-height: 95vh';

    public $model = InvoiceModel::class;

    protected $team;

    public function created()
    {
        $this->team = $this->model->team;
    }

    public function handle()
    {
        InvoiceService::payInvoice(new PayInvoiceDto([
            'pay_next_installment' => true, // If there is a payment installment, it will pay just it, if not it will pay the whole invoice
            'invoice_id' => $this->model->id,
            'payment_method_id' => $this->model->payment_method_id ?? request('payment_method_id'),
            'payment_term_id' => $this->model->payment_term_id ?? request('payment_term_id'),
            'address' => parsePlaceFromRequest('address1'),
        ]));
    }

    public function render()
    {
        return _Rows(
            _Html('finance.pay-invoice')->class('text-center text-2xl font-semibold mb-6'),
            _CardLevel4(
                $this->model->payment_term_id ? null : _Select('finance.how-many-installments')->name('payment_term_id')
                    ->options($this->getPaymentInstallments())
                    ->selfGet('getPaymentSchedule')->inPanel('payment-schedule')
                    ->class('mb-1'),
                _Rows(
                    _Html('finance.payment-schedule')->class('border-b border-gray-300 font-semibold mt-1'),
                    _Panel(
                        $this->getPaymentSchedule($this->model->payment_term_id),
                    )->id('payment-schedule'),
                ),
            )->class('p-6 mb-6'),
            _ButtonGroup('finance.pay-with')->name('payment_method_id')
                ->options($this->getPaymentMethods())
                ->selfGet('getPaymentMethodFields')->inPanel('payment-method-fields')
                ->containerClass('flex flex-col gap-2')
                ->class('vlButtonGroupVertical mb-6')
                ->optionClass('cursor-pointer text-center px-4 py-3 font-medium text-lg !rounded-lg !shadow-none')
                ->selectedClass('bg-warning text-white', 'text-greenmain bg-level4'),
            _CardLevel4(
                _Panel(
                    $this->getPaymentMethodFields($this->model->payment_method_id?->value),
                )->id('payment-method-fields'),

                $this->model->address ? null :
                    _CanadianPlace(),

            )->class('p-6'),
            _SubmitButton('finance.pay')
                ->closeModal()
                ->closeModal()
                ->refresh('dashboard-view')
                ->class('w-full')
                ->alert('translate.paid-successfully'),
        )->class('p-6');
    }

    public function getPaymentMethodFields($paymentMethodId = null)
    {
        if (!$paymentMethodId) {
            return _Html('finance.select-payment-method')->class('text-center text-gray-500');
        }

        $paymentMethod = PaymentMethodEnum::from($paymentMethodId);

        return _Rows(
            $paymentMethod->form($this->model),
        );
    }

    public function getPaymentSchedule($paymentTermId = null)
    {
        if (!$paymentTermId) {
            return _Html('finance.select-installment')->class('text-center text-gray-500');
        }

        $paymentTerm = PaymentTerm::findOrFail($paymentTermId);

        return _Rows(
            $paymentTerm->preview($this->model)
        )->class('mt-2');
    }

    protected function getPaymentMethods()
    {
        return PaymentMethod::whereIn('id', $this->model->possible_payment_methods ?? [])->isOnlinePayment()->pluck('name', 'id');
    }

    protected function getPaymentInstallments()
    {
        return PaymentTerm::whereIn('id', $this->model->possible_payment_terms ?? [])->pluck('term_name', 'id');
    }

    public function rules()
    {
        return [
            'payment_method_id' => 'required|exists:fin_payment_methods,id',
            'payment_term_id' => 'nullable|exists:fin_payment_terms,id',
        ];
    }
}
