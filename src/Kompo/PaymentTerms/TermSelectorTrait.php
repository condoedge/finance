<?php

namespace Condoedge\Finance\Kompo\PaymentTerms;

use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\PaymentTermTypeEnum;

trait TermSelectorTrait
{
    protected function getPaymentTermsSelector()
    {
        return _Rows(
            _Select('finance-payment-terms')->name('payment_term_type', false)
            ->options(PaymentTermTypeEnum::optionsWithLabels())
            ->selfGet('getPaymentTerms')->inPanel('payment-terms-panel')
            ->class('mb-2'),
            _Panel()->id('payment-terms-panel')
        );
    }

    public function getPaymentTerms($paymentTermType = null)
    {
        if (!$paymentTermType) {
            return null;
        }

        $paymentTermType = PaymentTermTypeEnum::tryFrom($paymentTermType);
        $element = null;

        if ($paymentTermType == PaymentTermTypeEnum::COD) {
            return _Hidden()->name('possible_payment_terms')->value([PaymentTerm::where('term_type', $paymentTermType->value)->pluck('id')->first()]);
        }

        if ($paymentTermType == PaymentTermTypeEnum::INSTALLMENT) {
            $element = _MultiSelect('finance-payment-terms');
        } else {
            $element = _Select('finance-payment-terms');
        }

        return $element->name('possible_payment_terms')->options(PaymentTerm::where('term_type', $paymentTermType->value)->pluck('term_name', 'id')->all())
            ->default(PaymentTerm::whereIn('id', $this->getDefaultPaymentTerms())->where('term_type', $paymentTermType->value)->pluck('id')->all())
            ->class('mb-2');
    }

    protected function getDefaultPaymentTerms()
    {
        return [];
    }
}
