<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayResolverInterface;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentFlowEnum;
use Condoedge\Finance\Billing\Core\PaymentLog;
use Condoedge\Finance\Facades\InvoiceModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentProcessor;
use Condoedge\Finance\Kompo\Common\PaymentUnavailableNotice;
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
        try {
            $result = InvoiceService::payInvoice(new PayInvoiceDto([
                'pay_next_installment' => true, // If there is a payment installment, it will pay just it, if not it will pay the whole invoice
                'invoice_id' => $this->model->id,
                'payment_method_id' => $this->model->payment_method_id ?? request('payment_method_id'),
                'payment_term_id' => $this->model->payment_term_id ?? request('payment_term_id'),
                'address' => parsePlaceFromRequest('address1'),
                'request_data' => request()->all()
            ]));
        } catch (\Exception $e) {
            abort(400, __('error-payment-failed'));
        }


        if ($result->isPending) {
            return $result->executeActionIntoKompoPanel();
        }

        if ($result->success) {
            return response()->kompoMulti([
                response()->closeModal(),
                response()->closeModal(),
                response()->kompoRun('() => {utils.removeLoadingScreen()}'),
                response()->kompoAlert('finance-paid-successfully'),
                response()->kompoRefresh('dashboard-view'),
            ]);
        }

        if (!$result->success) {
            abort(400, __('error-payment-failed'));
        }
    }

    public function render()
    {
        $paymentMethods = collect($this->getPaymentMethods());

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
                ->options($paymentMethods)
                ->default($paymentMethods->count() == 1 ? $paymentMethods->first() : null)
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
                ->onError(fn ($e) => $e->inAlert('error-icon', 'vlAlertError')->run('() => {utils.removeLoadingScreen()}')->closeModal())
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
        $context = new PaymentContext(
            payable: $this->model,
            paymentMethod: $paymentMethod,
        );

        // Pre-form gate: preview the resolver chain before rendering. If no
        // provider is currently healthy, show the friendly notice and log it.
        $chain = app(PaymentGatewayResolverInterface::class)->previewChain($context);

        if (empty($chain)) {
            PaymentLog::unavailable($context, 'no_healthy_provider');
            return new PaymentUnavailableNotice(null, [
                'method' => $paymentMethod->value,
                'reason' => 'no_healthy_provider',
            ]);
        }

        $primary = $chain[0];

        // Hosted flow: no inline form to render. Submitting the modal's main
        // Pay button kicks off payInvoice → processPayment → preload → REDIRECT
        // action, which redirects the browser to the provider's hosted page.
        if ($primary->getCheckoutFlow()->isHosted()) {
            return _Html(__('finance.you-will-be-redirected-to', [
                'provider' => $primary->getDisplayName(),
            ]))->class('text-sm text-gray-600 text-center');
        }

        return $primary->getPaymentForm($context)
            ?? _Html($paymentMethod->label())->class('text-center text-gray-800');
    }

    public function getPaymentSchedule($paymentTermId = null)
    {
        if (!$paymentTermId) {
            return _Html('finance.select-installment')->class('text-center text-gray-500');
        }

        $paymentTerm = PaymentTerm::withTrashed()->findOrFail($paymentTermId);

        return _Rows(
            $paymentTerm->preview($this->model)
        )->class('mt-2');
    }

    protected function getPaymentMethods()
    {
        // Show only methods the team's providers can actually process. The
        // resolver enforces provider capability (a method never shows unless a
        // provider genuinely supports it) and the primary-vs-fallback rule
        // controlled by config('kompo-finance.offer_fallback_provider_methods').
        $methods = PaymentMethod::whereIn('id', $this->model->possible_payment_methods ?? [])
            ->isOnlinePayment()
            ->get();

        $resolver = app(PaymentGatewayResolverInterface::class);

        return $methods->filter(function (PaymentMethod $method) use ($resolver) {
            $context = new PaymentContext(
                payable: $this->model,
                paymentMethod: PaymentMethodEnum::from($method->id),
            );
            return $resolver->isMethodAvailable($context);
        })->pluck('name', 'id');
    }

    protected function getPaymentInstallments()
    {
        return PaymentTerm::whereIn('id', $this->model->possible_payment_terms ?? [])->pluck('term_name', 'id');
    }
}
