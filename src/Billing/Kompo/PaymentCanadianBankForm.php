<?php

namespace Condoedge\Finance\Billing\Kompo;

use Condoedge\Utils\Kompo\Common\Form;

class PaymentCanadianBankForm extends Form
{
    public $class = 'p-0';

    public function render()
    {
        return _Rows(
            _Alert('translate.finance-bank-account-authorization-notice')
                ->icon('information-circle')
                ->class('mb-4'),
                
            _Input('translate.finance-account-holder-name')
                ->name('account_holder_name')
                ->placeholder('John Doe')
                ->rules('required|string|min:2')
                ->class('mb-2'),
                
            _ValidatedInput('translate.finance-transit-number')
                ->name('transit_number')
                ->placeholder('12345')
                ->allow('^[0-9]{0,5}$')
                ->validate('^[0-9]{5}$')
                ->class('mb-2')
                ->hint('translate.finance-transit-number-hint'),
                
            _ValidatedInput('translate.finance-institution-number')
                ->name('institution_number')
                ->placeholder('001')
                ->allow('^[0-9]{0,3}$')
                ->validate('^[0-9]{3}$')
                ->class('mb-2')
                ->hint('translate.finance-institution-number-hint'),
                
            _ValidatedInput('translate.finance-account-number')
                ->name('account_number')
                ->placeholder('1234567890')
                ->allow('^[0-9]{0,12}$')
                ->validate('^[0-9]{7,12}$')
                ->class('mb-2')
                ->hint('translate.finance-account-number-hint'),
                
            _Checkbox('translate.finance-authorize-debit')
                ->name('authorize_debit')
                ->rules('required|accepted')
                ->class('mt-4')
                ->label('translate.finance-i-authorize-debit-from-account')
        );
    }
    
    public function rules()
    {
        return [
            'account_holder_name' => 'required|string|min:2|max:255',
            'transit_number' => 'required|digits:5',
            'institution_number' => 'required|digits:3',
            'account_number' => 'required|digits_between:7,12',
            'authorize_debit' => 'required|accepted',
        ];
    }
}
