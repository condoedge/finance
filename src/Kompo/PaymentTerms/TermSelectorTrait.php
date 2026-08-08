<?php

namespace Condoedge\Finance\Kompo\PaymentTerms;

use Condoedge\Finance\Models\PaymentTerm;
use Condoedge\Finance\Models\PaymentTermTypeEnum;

trait TermSelectorTrait
{
    protected function getPaymentTermsSelector($selectPaymentTermId = null, $paymentTermName = 'possible_payment_terms')
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
            ->selfGet('getPaymentTerms', ['payment_term_name' => $paymentTermName])->inPanel('payment-terms-panel')
            ->when($onChangeCallback, fn ($el) => $el->onChange($onChangeCallback))
            ->class('mb-2'),
            _Panel(
                $this->getPaymentTerms($selectPaymentTermId, $paymentTermName)
            )->id('payment-terms-panel')->class('z-10')
        );
    }

    public function getPaymentTerms($paymentTermType = null, $paymentTermName = 'possible_payment_terms')
    {
        $paymentTermType = request('payment_term_type', $paymentTermType);
        $paymentTermName = request('payment_term_name', $paymentTermName);

        $paymentTermType = $paymentTermType instanceof PaymentTermTypeEnum ?
            $paymentTermType :
            PaymentTermTypeEnum::tryFrom((int) $paymentTermType);

        if (!$paymentTermType) {
            return null;
        }

        if ($paymentTermType == PaymentTermTypeEnum::COD) {
            return _Hidden()->name($paymentTermName)->value(array_filter([PaymentTerm::where('term_type', $paymentTermType->value)->pluck('id')->first()]));
        }

        $isInstallment = $paymentTermType == PaymentTermTypeEnum::INSTALLMENT;
        $element = $isInstallment ? _MultiSelect('finance-payment-terms') : _Select('finance-payment-terms');

        // possible_payment_terms holds terms of every type, so the model must not fill this
        // element: an id that isn't one of the options below comes back unusable from the front.
        $selectedIds = PaymentTerm::whereIn('id', $this->getDefaultPaymentTerms())
            ->where('term_type', $paymentTermType->value)
            ->pluck('id')->all();

        $onChangeCallback = $this->onChangePaymentTerms();

        return $element->name($paymentTermName, false)
            ->options(PaymentTerm::where('term_type', $paymentTermType->value)->pluck('term_name', 'id')->all())
            ->default($isInstallment ? $selectedIds : ($selectedIds[0] ?? null))
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
