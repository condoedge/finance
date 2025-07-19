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
        $customer = null;

        if (!$modelInstance) {
            $customer = CustomerService::createOrUpdate(new CreateOrUpdateCustomerDto([
                'name' => request('name'),
                'email' => request('email'),
                'phone' => request('phone'),
                'address' => parsePlaceFromRequest('address'),
            ]));
        } else {
            $customer = CustomerService::createFromCustomable(new CreateCustomerFromCustomable([
                'customable_id' => $modelInstance->id,
                'customable_type' => request('from_model'),
                'address' => parsePlaceFromRequest('address'),
            ]));
        }

        return _Html()->attr([
            'data-id' => $customer->id,
            'data-name' => $customer->name,
        ]);
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
                _SubmitButton('finance-save')
                    ->inPanel('customer-after-save-info')
                    ->run($this->jsToSelectCustomer())->closeModal()->refresh($this->refreshId),
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

            _CanadianPlace()->name('address')
                ->class('place-input-without-visual'),
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

        if (!$modelInstance?->getFirstValidAddressLabel()) {
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

    public function jsToSelectCustomer()
    {
        return '() => {
            const waitForCustomerInfo = async () => {
                            return new Promise((resolve) => {
                                const interval = setInterval(() => {
                                    if ($("#customer-after-save-info").children().length > 0) {
                                        clearInterval(interval);
                                        resolve();
                                    }
                                }, 30);
                            });
                        };

                        const waitUntilOptionAppear = async () => {
                            return new Promise((resolve) => {
                                const interval = setInterval(() => {
                                    if ($(".select-on-create").find(".vlOptions").find("span[data-id]").length) {
                                        clearInterval(interval);
                                        resolve();
                                    }
                                }, 30);
                            });
                        };

                        waitForCustomerInfo().then(() => {
                            const customerInfo = $("#customer-after-save-info").children();
                            const customerName = customerInfo.data("name");
                            const customerId = customerInfo.data("id");

                            $(".select-on-create").find("input").each(function() {
                                $(this).val(customerName);
                                $(this).get(0).dispatchEvent(new Event("input", { bubbles: true }));
                                $(this).get(0).dispatchEvent(new Event("click", { bubbles: true }));
                            });

                                                        waitUntilOptionAppear().then(() => {
                                $(".select-on-create").find(".vlOptions").find("span[data-id]").each(function() {
                                    if ($(this).data("id") == customerId) {
                                        $(this).get(0).dispatchEvent(new Event("click", { bubbles: true }));
                                    }
                                });
                            });
                        });
            }';
    }
}
