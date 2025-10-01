<?php

namespace Condoedge\Finance\Kompo\PaymentTerms;

use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\PaymentTermTypeEnum;

trait TermSelectorTrait
{
    protected function getPaymentTermsSelector($selectPaymentTermId = null)
    {
        $paymentTermTypes = PaymentTerm::distinct()->pluck('term_type');
        $onChangeCallback = $this->onChangePaymentTerms();

        return _Rows(
            _Select('finance-payment-terms')->name('payment_term_type', false)
            ->options(
                collect(PaymentTermTypeEnum::cases())->filter(fn ($enum) => $paymentTermTypes->contains($enum))
                ->mapWithKeys(fn ($enum) => [$enum->value => $enum->label()])->all()
            )
            ->default($selectPaymentTermId)
            ->selfGet('getPaymentTerms')->inPanel('payment-terms-panel')
            ->when($onChangeCallback, fn ($el) => $el->onChange($onChangeCallback))
            ->class('mb-2'),
            _Panel(
                $this->getPaymentTerms($selectPaymentTermId)
            )->id('payment-terms-panel')
        );
    }

    public function getPaymentTerms($paymentTermType = null)
    {
        if (!$paymentTermType) {
            return null;
        }

        $paymentTermType = is_numeric($paymentTermType) ? PaymentTermTypeEnum::tryFrom($paymentTermType) : $paymentTermType;
        $element = null;

        if ($paymentTermType == PaymentTermTypeEnum::COD) {
            return _Hidden()->name('possible_payment_terms')->value([PaymentTerm::where('term_type', $paymentTermType->value)->pluck('id')->first()]);
        }

        if ($paymentTermType == PaymentTermTypeEnum::INSTALLMENT) {
            $element = _MultiSelect('finance-payment-terms');
        } else {
            $element = _Select('finance-payment-terms');
        }

        $onChangeCallback = $this->onChangePaymentTerms();

        return $element->name('possible_payment_terms')->options(PaymentTerm::where('term_type', $paymentTermType->value)->pluck('term_name', 'id')->all())
            ->default(PaymentTerm::whereIn('id', $this->getDefaultPaymentTerms())->where('term_type', $paymentTermType->value)->pluck('id')->all())
            ->when($onChangeCallback, fn ($el) => $el->onChange($onChangeCallback))
            ->class('mb-2');
    }

    protected function onChangePaymentTerms()
    {
        return null;
    }

    protected function getDefaultPaymentTerms()
    {
        return [];
    }
}
