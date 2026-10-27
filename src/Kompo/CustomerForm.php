<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\CustomerService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Customers\CreateOrUpdateCustomerDto;
use Condoedge\Finance\Services\Customer\CustomerService as CustomerServiceImplementation;

/**
 * External customers only: people and teams are found in the invoice recipient search
 * and get their customer row at save.
 */
class CustomerForm extends Modal
{
    protected $_Title = 'finance-external-customer-title';
    public $model = CustomerModel::class;

    protected $teamId;
    protected $refreshId;

    public function created()
    {
        $this->refreshId = $this->prop('refresh_id');
    }

    public function beforeSave()
    {
        $this->model->team_id = $this->teamId ?? currentTeamId();
    }

    public function handle()
    {
        $customer = CustomerService::createOrUpdate(new CreateOrUpdateCustomerDto([
            'name' => request('name'),
            'email' => request('email'),
            'phone' => request('phone'),
            'address' => parsePlaceFromRequest('address'),
        ]));

        return ['option' => [CustomerServiceImplementation::RECIPIENT_EXTERNAL . ':' . $customer->id => $customer->name]];
    }

    public function body()
    {
        return _Rows(
            _Rows($this->manualForm()),
            _FlexEnd(
                $this->refreshId
                    ? _SubmitButton('finance-save')->closeModal()->refresh($this->refreshId)
                    : _SubmitButton('finance-save'),
            ),
        );
    }

    public function manualForm()
    {
        return [
            _Input('finance-name')->name('name'),

            _InputEmail('finance-email')->name('email'),

            _InternationalPhoneInput('finance-phone')->name('phone'),

            _CanadianPlace()->name('address')
                ->class('place-input-without-visual'),
        ];
    }
}
