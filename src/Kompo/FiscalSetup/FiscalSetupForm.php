<?php

namespace Condoedge\Finance\Kompo\FiscalSetup;

use Condoedge\Finance\Facades\FiscalYearService;
use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Utils\Kompo\Common\Form;

class FiscalSetupForm extends Form
{
    public $model = FiscalYearSetup::class;

    public function created()
    {
        $this->model(FiscalYearSetup::getActiveForTeam());
    }

    public function handle()
    {
        FiscalYearService::setupFiscalYear(currentTeamId(), carbon(request('fiscal_start_date')));
    }

    public function render()
    {
        return _CardGray100(
            _Date('translate.fiscal-start-date')->name('fiscal_start_date'),

            _FlexEnd(
                _SubmitButton('generic.save')->alert('translate.saved')->refresh('finance-fiscal-setup-page'),
            ),
        )->p4();
    }

    public function rules()
    {
        return [
            'fiscal_start_date' => 'required|date',
        ];
    }
}