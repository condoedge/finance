<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Utils\Kompo\Common\Form;

class GlTransactionLineForm extends Form
{
    public $model = GlTransactionLine::class;
    public $class = 'align-top';
    
    protected $isReadOnly = false;
    protected $transactionType;
    protected $teamId;
    
    public function created()
    {
        $this->isReadOnly = $this->prop('readonly', false);
        $this->transactionType = $this->prop('transaction_type', 1);
        $this->teamId = $this->prop('team_id', currentTeamId());
    }
    
    public function render()
    {
        return [
            _AccountsSelect()->default($this->model->account?->getLastSegmentValue()->id),
            
            // Line description
            _Input()
                ->name('line_description')
                ->placeholder('translate.finance-optional-description')
                ->maxlength(255),
            
            // Debit amount
            _Input()
                ->name('debit_amount', false)
                ->default($this->model->debit_amount?->toFloat() ?? 0)
                ->type('number')
                ->step('0.01')
                ->min(0)
                ->placeholder('0.00')
                ->class('text-right w-32'),
            
            // Credit amount
            _Input()
                ->default($this->model->credit_amount?->toFloat() ?? 0)
                ->name('credit_amount', false)
                ->type('number')
                ->step('0.01')
                ->min(0)
                ->placeholder('0.00')
                ->class('text-right w-32'),
            
            // Remove button
            $this->isReadOnly ? null :
                _DeleteLink()->byKey($this->model)
        ];
    }
    
    public function rules()
    {
        return [
            'line_description' => 'nullable|string|max:255',
            'debit_amount' => 'nullable|numeric|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
        ];
    }
}
