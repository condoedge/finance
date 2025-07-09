<?php

namespace Condoedge\Finance\Billing\Kompo;

use Carbon\Carbon;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Utils\Kompo\Common\Form;

class PaymentCreditCardForm extends Form
{
    public $model = Invoice::class;

    public $class = 'p-0';

    public function render()
    {
        return _Rows(
            _Input('finance.name-on-card')->name('complete_name')->class('mb-2')->placeholder('translate.complete-name'),
            _CreditCardInput('translate.credit-card-number')->placeholder('4444 4213 1234 5678')->attr(['autocomplete' => "off"])->name('card_information')->class('mb-2'),
            _FlexBetween(
                _DateTextInput('translate.expiration')->name('expiration_date')->class('mb-2')->attr(['autocomplete' => "off"])->format('MM / YY')->validateJustFuture(),
                _ValidatedInput('translate.cvc')->placeholder('CVC')->name('card_cvc')->class('mb-2')
                    ->attr(['autocomplete' => "off"])
                    ->allow('^[0-9]{0,3}$')
                    ->validate('^[0-9]{3}$'),
            )->class('gap-4'),
        );
    }

    protected function separateExpirationDate()
    {
        $expirationDate = Carbon::createFromFormat('d/m/Y', request()->offsetGet('expiration_date'));

        request()->merge([
            'expiry_mm' => $expirationDate->format('m'),
            'expiry_yy' => $expirationDate->format('y'),
        ]);
    }

    protected function separateNames()
    {
        $name = request()->offsetGet('complete_name');
        $names = explode(' ', $name);

        $firstName = array_shift($names);
        $lastName = implode(' ', $names);

        if (!$firstName || !$lastName) {
            throwValidationError('complete_name', __('finance.incomplete-name'));
        }

        request()->merge([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    public function rules()
    {
        return array_merge([
            'complete_name' => 'required',

            'card_information' => 'required|digits:16',
            'expiration_date' => 'required|date',
            'card_cvc' => 'required|digits:3',
        ]);
    }
}
