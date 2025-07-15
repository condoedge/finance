<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentProcessor;
use Condoedge\Finance\Models\Dto\Invoices\PayInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentMethod;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Utils\Kompo\Common\Form;

class InvoicePayModal extends Form
{
    public $class = 'overflow-y-auto mini-scroll max-w-lg';
    public $style = 'max-height: 95vh; height: 95vh; width: 98vw;';

    /**
     * @var Invoice
     */
    public $model = InvoiceModel::class;

    protected $team;

    public function created()
    {
        $this->team = $this->model->customer->team;
    }

    public function handle()
    {
        $result = InvoiceService::payInvoice(new PayInvoiceDto([
            'pay_next_installment' => true, // If there is a payment installment, it will pay just it, if not it will pay the whole invoice
            'invoice_id' => $this->model->id,
            'payment_method_id' => $this->model->payment_method_id ?? request('payment_method_id'),
            'payment_term_id' => $this->model->payment_term_id ?? request('payment_term_id'),
            'address' => parsePlaceFromRequest('address1'),
            'request_data' => request()->all()
        ]));

        if ($result->isPending) {
            return $result->executeActionIntoKompoPanel();
        }

        if ($result->success) {
            return $this->successElsEvents();
        }

        if (!$result->success) {
            abort(400, __('error-payment-failed'));
        }
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
            $this->model->payment_method_id ? null : _ButtonGroup('finance.pay-with')->name('payment_method_id')
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
                ->onError(fn ($e) => $e->inAlert()->run('() => {utils.removeLoadingScreen()}')->closeModal())
                ->id('pay-button')
                ->inPanel('after-pay-invoice')
                ->class('w-full'),
            _Panel()->id('after-pay-invoice'),
        )->class('p-6');
    }

    public function getPaymentMethodFields($paymentMethodId = null)
    {
        if (!$paymentMethodId) {
            return _Html('finance.select-payment-method')->class('text-center text-gray-500');
        }

        $paymentMethod = PaymentMethodEnum::from($paymentMethodId);

        return PaymentProcessor::getPaymentForm(new PaymentContext(
            payable: $this->model,
            paymentMethod: $paymentMethod,
        )) ?? _Html($paymentMethod->label())->class('text-center text-gray-800');
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

    protected function successElsEvents()
    {
        return _Rows(
            _Hidden()->onLoad(fn ($e) => $e->run('() => {
                        $("#pay-invoice-success").click();
                    }')),
            _Button()->id('pay-invoice-success')
                ->class('hidden')
                ->closeModal()
                ->closeModal()
                ->refresh('dashboard-view')
                ->run('() => {utils.removeLoadingScreen()}')
                ->alert('finance-paid-successfully')
                ->onError(fn ($e) => $e->run('() => {utils.removeLoadingScreen()}')->closeModal())
        );
    }

    public function rules()
    {
        return [
            // 'payment_method_id' => 'required|exists:fin_payment_methods,id',
            // 'payment_term_id' => 'nullable|exists:fin_payment_terms,id',
        ];
    }
}
