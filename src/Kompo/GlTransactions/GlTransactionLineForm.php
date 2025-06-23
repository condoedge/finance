<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\GlAccount;
use Kompo\Form;

class GlTransactionLineForm extends Form
{
    public $model = GlTransactionLine::class;
    
    protected $isReadOnly = false;
    protected $transactionType;
    
    public function created()
    {
        $this->isReadOnly = $this->prop('readonly', false);
        $this->transactionType = $this->prop('transaction_type');
    }
    
    public function render()
    {
        return _TableRow(
            // Line number (auto-generated)
            _Td(
                _Html()->class('line-number text-center')
            )->class('w-12'),
            
            // Account selection
            _Td(
                $this->renderAccountSelect()
            )->class('w-64'),
            
            // Line description
            _Td(
                _Input()
                    ->name('line_description')
                    ->placeholder('finance-optional-description')
                    ->maxlength(255)
                    ->disabled($this->isReadOnly)
            ),
            
            // Debit amount
            _Td(
                _Input()
                    ->name('debit_amount')
                    ->type('number')
                    ->step('0.01')
                    ->min(0)
                    ->placeholder('0.00')
                    ->disabled($this->isReadOnly)
                    ->class('text-right')
                    ->onChange('updateTotals()')
                    ->onInput('handleDebitCreditExclusion(this, "credit")')
            )->class('w-32'),
            
            // Credit amount
            _Td(
                _Input()
                    ->name('credit_amount')
                    ->type('number')
                    ->step('0.01')
                    ->min(0)
                    ->placeholder('0.00')
                    ->disabled($this->isReadOnly)
                    ->class('text-right')
                    ->onChange('updateTotals()')
                    ->onInput('handleDebitCreditExclusion(this, "debit")')
            )->class('w-32'),
            
            // Remove button
            _Td(
                $this->isReadOnly ? null :
                    _Button()->icon('trash')
                        ->class('text-danger')
                        ->emitDirect('removeRow')
            )->class('w-12 text-center'),
        );
    }
    
    /**
     * Render account selection field
     */
    protected function renderAccountSelect()
    {
        if ($this->isReadOnly) {
            return _Html($this->model->account_id ?? '---');
        }
        
        // Build query for available accounts
        $query = GlAccount::forTeam()->active();
        
        // For manual GL transactions, only show accounts that allow manual entry
        if ($this->transactionType === \Condoedge\Finance\Models\GlTransactionHeader::TYPE_MANUAL_GL) {
            $query->allowManualEntry();
        }
        
        // Get accounts with formatted display
        $accounts = $query
            ->get()
            ->mapWithKeys(function ($account) {
                $label = $account->account_id . ' - ' . ($account->account_description ?: __('finance-no-description'));
                return [$account->account_id => $label];
            });
        
        return _Select()
            ->name('account_id')
            ->options($accounts->prepend(__('finance-select-account'), ''))
            ->searchable()
            ->required()
            ->onChange('updateAccountInfo(this)');
    }
    
    public function js()
    {
        return <<<javascript
// Handle mutual exclusion of debit/credit
function handleDebitCreditExclusion(input, oppositeField) {
    const value = parseFloat(input.value) || 0;
    const row = $(input).closest('tr');
    const oppositeInput = row.find('[name$="[' + oppositeField + '_amount]"]')[0];
    
    if (value > 0 && oppositeInput) {
        oppositeInput.value = '';
    }
}

// Update line numbers
function updateLineNumbers() {
    $('#gl-transaction-lines tbody tr').each(function(index) {
        $(this).find('.line-number').text(index + 1);
    });
}

// Initialize when rows are added/removed
$(document).on('rowAdded', '#gl-transaction-lines', updateLineNumbers);
$(document).on('rowRemoved', '#gl-transaction-lines', updateLineNumbers);

// Initial update
$(document).ready(updateLineNumbers);
javascript;
    }
}
