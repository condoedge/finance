<?php

namespace Condoedge\Finance\Kompo;

use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Kompo\Common\Modal;

class CustomerForm extends Modal
{
    protected $_Title = 'translate.customer';
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
        if (request('from_model') && request('from_id')) {
            $fromModel = request('from_model');
            $fromId = request('from_id');

            $modelInstance = $fromModel::find($fromId);

            $modelInstance->upsertCustomerFromThisModel(currentTeamId());
        }
    }

    public function body()
    {
        return _Rows(
            // Create from other customable model or...
            _Select('translate.from-entity')->name('from_model', false)->class('!mb-2')
                ->options(CustomerModel::getCustomables()->mapWithKeys(function ($customable) {
                    return [$customable => $customable::getVisualName()];
                }))
                ->selfGet('getCustomableOptions')->inPanel('customableOptionsPanel'),

            _Panel(
                $this->manualForm()
            )->id('customableOptionsPanel'),

            _FlexEnd(
                _SubmitButton('translate.save')->closeModal()->refresh($this->refreshId),
            ),
        );
    }

    public function manualForm()
    {
        return [
            _Html('translate.or')->class('mb-4 mt-2'),

            _Input('translate.name')->name('name'),
        ];
    }

    public function getCustomableOptions()
    {
        $customableClass = request('from_model');

        if (!$customableClass) {
            return $this->manualForm();
        }

        return _Select($customableClass::getVisualName())->name('from_id')
            ->options($customableClass::getOptionsForCustomerForm());
    }
}