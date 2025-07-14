<?php

namespace Condoedge\Finance\Billing\Providers\Stripe\Form;

use Condoedge\Utils\Kompo\Common\Form;

class StripeCreditCardForm extends Form
{
    public function created()
    {
        $stripeJs = file_get_contents(__DIR__ . '/../../../resources/js/stripe.js');
        $stripeJs = str_replace('{{STRIPE_PUBLIC_KEY}}', config('kompo-finance.services.stripe.api_key'), $stripeJs);

        $this->onLoad(fn($e) => $e->run('() => {
            ' . $stripeJs . '
            
            activateStripe();
        }'));
    }

    public function render()
    {
        return _Rows(
            _FlexBetween(
                _Html('translate.stripe-secure-tag')->icon(_Sax('lock-1', 16))->class('text-xs flex font-medium'),
                _Img('images/vendor/kompo-finance/powered-by-stripe.svg')->class('w-24'),
            )->class('mb-4'),
            _Input()->name('complete_name')->class('mb-4')->placeholder('finance-cardholders-name'),
            _Html()->class('vlInputWrapper p-4')->id('card-element'),
            _Hidden('payment_method_id', false)->id('payment_method_id'),
        );
    }
}
