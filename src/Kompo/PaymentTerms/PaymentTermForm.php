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
        $fields = $this->getFieldsForTermType($paymentTermType, $this->model->settings ?? []);

        if (!count($fields)) {
            return null;
        }

        return _CardGray100(
            _Html(__('finance-with-values-settings-for', ['type' => $paymentTermType->label()]))->class('font-semibold mb-4 text-lg'),
            _Rows($fields),
        )->p4();
    }

    protected function getFieldsForTermType(PaymentTermTypeEnum $termType, array $settings = []): array
    {
        return match ($termType) {
            PaymentTermTypeEnum::COD => [],
            PaymentTermTypeEnum::NET => [
                _InputNumber('finance-days')->name('settings_days', false)->default($settings['days'] ?? null)->required(),
            ],
            PaymentTermTypeEnum::INSTALLMENT => [
                'periods' => _InputNumber('finance-periods')->name('settings_periods', false)->default($settings['periods'] ?? null)->required(),
                'interval_type' => _ButtonGroup('finance-interval-type')
                    ->optionClass('cursor-pointer text-center px-4 py-3 flex justify-center')
                    ->name('settings_interval_type', false)
                    ->default($settings['interval_type'] ?? 'months')
                    ->options([
                        'days' => __('finance-days'),
                        'months' => __('finance-months'),
                    ]),
                'interval' => _InputNumber('finance-interval')->name('settings_interval', false)->default($settings['interval'] ?? null),
            ],
        };
    }

}
