<?php

namespace Condoedge\Finance\Kompo\PaymentTerms;

use Condoedge\Finance\Facades\PaymentTermService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\PaymentTerms\CreateOrUpdatePaymentTermDto;
use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\PaymentTermTypeEnum;

class PaymentTermForm extends Modal
{
    public $_Title = 'finance-payment-term-form';
    public $model = PaymentTerm::class;

    public function handle()
    {
        PaymentTermService::createOrUpdatePaymentTerm(new CreateOrUpdatePaymentTermDto([
            'id' => $this->model->id,
            'term_name' => request('term_name'),
            'term_type' => (int) request('term_type'),
            'term_description' => request('term_description'),
            'settings' => collect(request()->all())->filter(fn ($_, $key) => str_contains($key, 'settings_'))
                ->mapWithKeys(fn ($value, $key) => [str_replace('settings_', '', $key) => $value])
                ->all(),
        ]));
    }

    public function body()
    {
        return _Rows(
            _Input('finance-term-name')->name('term_name')->label('finance-term_name')->required(),
            _Select('finance-term-type')->name('term_type')->label('finance-term_type')
                ->selfGet('getSettingsFields')->inPanel('settings-fields-panel')
                ->options(PaymentTermTypeEnum::optionsWithLabels())
                ->required(),
            _Panel(
                $this->getSettingsFields($this->model->term_type?->value)
            )->id('settings-fields-panel'),
            _Textarea('finance-term-description')->name('term_description'),
            _SubmitButton('generic.save')->closeModal()->browse('payment-terms-table')
                ->alert('finance-payment-term-saved'),
        );
    }

    public function getSettingsFields($paymentTermType = null)
    {
        if (!$paymentTermType) {
            return null;
        }

        $paymentTermType = PaymentTermTypeEnum::from($paymentTermType);

        if (!count($paymentTermType->settingsFields())) {
            return null;
        }

        return _CardGray100(
            _Html(__('finance-with-values-settings-for', ['type' => $paymentTermType->label()]))->class('font-semibold mb-4 text-lg'),
            _Rows(
                $paymentTermType->settingsFields($this->model->settings ?? [])
            ),
        )->p4();
    }

}
