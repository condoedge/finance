<?php

namespace Condoedge\Finance\Kompo\Taxes;

use Condoedge\Finance\Facades\AccountSegmentService;
use Condoedge\Finance\Facades\TaxModel;
use Condoedge\Finance\Kompo\Common\Modal;
use Condoedge\Finance\Models\TaxableLocation;
use Condoedge\Finance\Models\TaxableLocationTypeEnum;

class TaxForm extends Modal
{
    public $model = TaxModel::class;

    protected $_Title = 'finance-add-new-tax';

    public function beforeSave()
    {
        $this->model->taxable_location_id = request('taxable_location_id') ?: null;
        $this->model->rate = request('pct_rate') / 100;
        $this->model->account_id = AccountSegmentService::createAccountFromLastValue(request('account_id'))->id;
    }

    public function body()
    {
        $currentLocationType = $this->model->location?->type->value == 1 ? 1 : 2;

        return _Rows(
            _Input('finance-name')
                ->name('name')
                ->required(),
            _InputNumber('finance-rate')
                ->name('pct_rate', false)
                ->required()
                ->default($this->model->rate?->multiply(100)->toFloat()),

            !config('kompo-finance.taxes-have-locations') ? null :
                _Rows(
                    _ButtonGroup('translate.tax-location')->name('taxable_location_type', false)
                        ->selfGet('getLocationsList')->inPanel('list-locations')
                        ->when($this->model->location, fn($el) => $el->default($currentLocationType))
                        ->options([
                            1 => 'translate.finance-federal',
                            2 => 'translate.finance-provincial',
                        ])->optionClass('cursor-pointer text-center px-4 py-3 flex justify-center')
                        ->class('mb-2'),

                    _Panel(
                        $this->getLocationsList($currentLocationType)
                    )->id('list-locations'),
                ),

            _AccountsSelect('finance-account', $this->model->account)
                ->name('account_id', false)
                ->required(),

            _Date('finance-valid-from')->name('valide_from')
                ->default(now()->format('Y-m-d')),
            _FlexEnd(
                _SubmitButton('generic.save')->closeModal()
                    ->alert('finance-tax-saved-successfully')
                    ->refresh('taxes-table'),
            )->class('mt-3'),
        );
    }

    public function getLocationsList($taxableLocationType = null)
    {
        if (!$taxableLocationType) {
            return null;
        }

        $types = $taxableLocationType == 1 ? [TaxableLocationTypeEnum::FEDERAL] : [TaxableLocationTypeEnum::PROVINCE, TaxableLocationTypeEnum::TERRITORY];

        $locations = TaxableLocation::whereIn('type', $types)
            ->orderBy('name')
            ->pluck('name', 'id');

        if ($locations->isEmpty()) {
            return _Html('translate.finance-no-locations-found')->class('text-center mt-3');
        }

        if ($locations->count() == 1) {
            $locationId = $locations->keys()->first();

            return _Hidden()->name('taxable_location_id')
                ->value($locationId);
        }

        return _Select('translate.finance-select-location')->options($locations)
            ->name('taxable_location_id')
            ->default($this->model->taxable_location_id)
            ->class('mt-3')
            ->required();
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'pct_rate' => 'required|numeric|min:0.01|max:100',
            'account_id' => 'required|exists:fin_segment_values,id',
            'taxable_location_id' => 'nullable|exists:fin_taxable_locations,id',
        ];
    }
}
