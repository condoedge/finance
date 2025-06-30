<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Facades\AccountSegmentService;
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
        return _TableRow(
            _AccountsSelect(),
            
            // Line description
            _Input()
                ->name('line_description')
                ->placeholder('translate.finance-optional-description')
                ->maxlength(255)
                ->disabled($this->isReadOnly),
            
            // Debit amount
            _Input()
                ->name('debit_amount')
                ->type('number')
                ->step('0.01')
                ->min(0)
                ->placeholder('0.00')
                ->disabled($this->isReadOnly)
                ->class('text-right w-32')
                ->run('handleDebitCreditInput', ['field' => 'debit'])
                ->run('updateTotals'),
            
            // Credit amount
            _Input()
                ->name('credit_amount')
                ->type('number')
                ->step('0.01')
                ->min(0)
                ->placeholder('0.00')
                ->disabled($this->isReadOnly)
                ->class('text-right w-32')
                ->run('handleDebitCreditInput', ['field' => 'credit'])
                ->run('updateTotals'),
            
            // Remove button
            $this->isReadOnly ? null :
                _DeleteLink()->byKey($this->model)->run('updateTotals')
        );
    }
    
    public function js()
    {
        return <<<javascript
            // Handle mutual exclusion of debit/credit
            function handleDebitCreditInput(params, el) {
                const field = params.field;
                const value = parseFloat(el.value) || 0;
                const row = el.closest('tr');
                
                if (field === 'debit' && value > 0) {
                    const creditInput = row.querySelector('input[name$="[credit_amount]"]');
                    if (creditInput) creditInput.value = '';
                } else if (field === 'credit' && value > 0) {
                    const debitInput = row.querySelector('input[name$="[debit_amount]"]');
                    if (debitInput) debitInput.value = '';
                }
            }
            javascript;
    }
    
    public function rules()
    {
        return [
            'account_id' => 'required|exists:fin_gl_accounts,account_id',
            'line_description' => 'nullable|string|max:255',
            'debit_amount' => 'nullable|numeric|min:0',
            'credit_amount' => 'nullable|numeric|min:0',
        ];
    }
}
