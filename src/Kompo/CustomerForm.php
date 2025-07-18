<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\CustomerService;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\Dto\Customers\CreateCustomerFromCustomable;
use Condoedge\Finance\Models\Dto\Customers\CreateOrUpdateCustomerDto;
use Illuminate\Database\Eloquent\Relations\Relation;

class CustomerForm extends Modal
{
    protected $_Title = 'finance-customer';
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
        $modelInstance = $this->getModelRelationInstance();

        if (!$modelInstance) {
            CustomerService::createOrUpdate(new CreateOrUpdateCustomerDto([
                'name' => request('name'),
                'email' => request('email'),
                'phone' => request('phone'),
                'address' => parsePlaceFromRequest('address'),
            ]));
        } else {
            CustomerService::createFromCustomable(new CreateCustomerFromCustomable([
                'customable_id' => $modelInstance->id,
                'customable_type' => request('from_model'),
                'address' => parsePlaceFromRequest('address'),
            ]));
        }
    }

    public function body()
    {
        return _Rows(
            // Create from other customable model or...
            _Select('finance-from-entity')->name('from_model', false)->class('!mb-2')
                ->options(CustomerService::getValidCustomableModels()->mapWithKeys(function ($customable, $morphableType) {
                    return [$morphableType => $customable::getVisualName()];
                }))
                ->selfGet('getCustomableOptions')->inPanel('customableOptionsPanel'),
            _Panel(
                $this->manualForm()
            )->id('customableOptionsPanel'),
            _FlexEnd(
                _SubmitButton('finance-save')->closeModal()->refresh($this->refreshId),
            ),
        );
    }

    public function manualForm()
    {
        return [
            _Html('finance-or')->class('mb-4 mt-2'),

            _Input('finance-name')->name('name'),

            _InputEmail('finance-email')->name('email'),

            _InputPhone('finance-phone')->name('phone'),

            _Place()->name('address'),
        ];
    }

    public function getCustomableOptions()
    {
        if (!request('from_model')) {
            return $this->manualForm();
        }

        $customableClass = Relation::morphMap()[request('from_model')];

        if (!$customableClass) {
            return $this->manualForm();
        }

        return _Rows(
            _Select($customableClass::getVisualName())->name('from_id')
                ->selfPost('ensureAddress')->withAllFormValues()->inPanel('address-panel')
                ->searchOptions(3, 'searchCustomables')
                ->ajaxPayload([
                    'from_model' => request('from_model'),
                ]),
            _Panel()->id('address-panel'),
        );
    }

    public function searchCustomables()
    {
        $customableClass = Relation::morphMap()[request('from_model')];
        $searchTerm = request('search');

        return $customableClass::getOptionsForCustomerForm($searchTerm);
    }

    public function ensureAddress()
    {
        $modelInstance = $this->getModelRelationInstance();

        if (!$modelInstance->getFirstValidAddress()) {
            return _Place()->name('address');
        }

        return null;
    }

    protected function getModelRelationInstance()
    {
        if (!request('from_model') || !request('from_id')) {
            return null;
        }

        return getModelFromMorphable(request('from_model'), request('from_id'));
    }
}
