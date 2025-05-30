<?php

namespace Condoedge\Finance\Kompo\GL;

use Kompo\Form;
use Condoedge\Finance\Models\GL\FiscalYearSetup;
use Condoedge\Finance\Models\GL\FiscalPeriod;

class FiscalSetupForm extends Form
{
    public $model = FiscalYearSetup::class;

    public function created()
    {
        $this->model = FiscalYearSetup::getActive() ?: new FiscalYearSetup();
    }

    public function render()
    {
        return [
            _Title('Fiscal Year Setup')->class('text-2xl font-bold mb-6'),
            
            _Columns(
                _Date('company_fiscal_start_date')
                    ->label('Company Fiscal Start Date')
                    ->placeholder('Select fiscal year start date')
                    ->required(),
                
                _Textarea('notes')
                    ->label('Notes')
                    ->placeholder('Optional notes about fiscal year setup')
                    ->rows(3)
            ),
            
            _SubmitButton('Save Fiscal Setup')->class('btn-primary mt-4'),
            
            $this->model->exists ? $this->renderPeriods() : null
        ];
    }

    protected function renderPeriods()
    {
        return [
            _Title('Fiscal Periods')->class('text-xl font-semibold mt-8 mb-4'),
            
            _Button('Create Periods')
                ->class('btn-secondary mb-4')
                ->onClick(fn() => redirect()->to('fiscal-periods/create')),
                
            new FiscalPeriodsTable()
        ];
    }

    public function rules()
    {
        return [
            'company_fiscal_start_date' => 'required|date',
            'notes' => 'nullable|string'
        ];
    }

    public function authorize()
    {
        return true; // Add proper authorization
    }
}
