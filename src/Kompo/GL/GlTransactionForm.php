<?php

namespace Condoedge\Finance\Kompo\GL;

use Kompo\Form;
use Condoedge\Finance\Models\GL\GlTransaction;
use Condoedge\Finance\Models\GL\GlAccount;
use Condoedge\Finance\Models\GL\FiscalPeriod;
use Carbon\Carbon;

class GlTransactionForm extends Form
{
    public $model = GlTransaction::class;
    protected $entries = [];

    public function created()
    {
        if ($this->model->exists) {
            $this->entries = $this->model->glEntries->toArray();
        } else {
            // Initialize with two empty entries
            $this->entries = [
                ['account_id' => '', 'line_description' => '', 'debit_amount' => 0, 'credit_amount' => 0],
                ['account_id' => '', 'line_description' => '', 'debit_amount' => 0, 'credit_amount' => 0]
            ];
        }
    }

    public function render()
    {
        return [
            _Title($this->model->exists ? 'Edit GL Transaction' : 'Create Manual GL Transaction')
                ->class('text-2xl font-bold mb-6'),
                
            $this->renderTransactionHeader(),
            
            $this->renderTransactionEntries(),
            
            $this->renderTransactionSummary(),
            
            _FlexEnd(
                _Link('Cancel')
                    ->href(url()->previous())
                    ->class('btn btn-outline-secondary mr-2'),
                    
                _SubmitButton($this->model->exists ? 'Update Transaction' : 'Create Transaction')
                    ->class('btn-primary')
            )->class('mt-6')
        ];
    }

    protected function renderTransactionHeader()
    {
        return [
            _Title('Transaction Header')->class('text-lg font-semibold mb-4'),
            
            _Columns(
                _Date('fiscal_date')
                    ->label('Fiscal Date')
                    ->required()
                    ->default(now()->format('Y-m-d'))
                    ->onChange(fn() => $this->validatePeriod()),
                    
                _Input('transaction_description')
                    ->label('Description')
                    ->placeholder('Enter transaction description')
                    ->required()
                    ->maxlength(255)
            ),
            
            $this->model->exists ? $this->renderTransactionInfo() : null
        ];
    }

    protected function renderTransactionInfo()
    {
        if (!$this->model->exists) return null;
        
        return [
            _Columns(
                _Html("GL Transaction #: {$this->model->gl_transaction_number}")
                    ->class('font-mono font-bold'),
                    
                _Html("Fiscal Year: {$this->model->fiscal_year}"),
                
                _Html("Fiscal Period: {$this->model->fiscal_period}"),
                
                _Html("Status: " . ($this->model->isPeriodOpen() ? 'Open' : 'Closed'))
                    ->class($this->model->isPeriodOpen() ? 'text-green-600' : 'text-red-600')
            )->class('p-4 bg-gray-50 rounded mb-4')
        ];
    }

    protected function renderTransactionEntries()
    {
        return [
            _Title('Transaction Entries')->class('text-lg font-semibold mt-6 mb-4'),
            
            _Html('Each transaction must have at least two entries and total debits must equal total credits.')
                ->class('text-sm text-gray-600 mb-4'),
                
            _Div([
                $this->renderEntriesTable(),
                
                _Button('Add Entry')
                    ->class('btn btn-outline-primary mt-3')
                    ->onClick(fn() => $this->addEntry())
            ])->id('entries-container')
        ];
    }

    protected function renderEntriesTable()
    {
        $accountOptions = GlAccount::active()->manualEntryAllowed()
            ->get()
            ->mapWithKeys(function($account) {
                return [$account->account_id => $account->account_id . ' - ' . $account->account_description];
            })
            ->toArray();

        return _Table()->headers([
            _Th('Account'),
            _Th('Description'),
            _Th('Debit'),
            _Th('Credit'),
            _Th('Actions')
        ])->rows(
            collect($this->entries)->map(function($entry, $index) use ($accountOptions) {
                return [
                    _Select("entries[{$index}][account_id]")
                        ->options($accountOptions)
                        ->placeholder('Select account')
                        ->required()
                        ->value($entry['account_id'] ?? ''),
                        
                    _Input("entries[{$index}][line_description]")
                        ->placeholder('Entry description (optional)')
                        ->value($entry['line_description'] ?? ''),
                        
                    _Input("entries[{$index}][debit_amount]")
                        ->type('number')
                        ->step('0.01')
                        ->min(0)
                        ->placeholder('0.00')
                        ->value($entry['debit_amount'] ?? 0)
                        ->onChange(fn() => $this->clearCreditAmount($index)),
                        
                    _Input("entries[{$index}][credit_amount]")
                        ->type('number')
                        ->step('0.01')  
                        ->min(0)
                        ->placeholder('0.00')
                        ->value($entry['credit_amount'] ?? 0)
                        ->onChange(fn() => $this->clearDebitAmount($index)),
                        
                    _Button('Remove')
                        ->class('btn btn-sm btn-outline-danger')
                        ->onClick(fn() => $this->removeEntry($index))
                        ->if(count($this->entries) > 2)
                ];
            })
        )->class('table-responsive');
    }

    protected function renderTransactionSummary()
    {
        $totalDebits = collect($this->entries)->sum('debit_amount');
        $totalCredits = collect($this->entries)->sum('credit_amount');
        $difference = $totalDebits - $totalCredits;
        $isBalanced = abs($difference) < 0.01;

        return [
            _Title('Transaction Summary')->class('text-lg font-semibold mt-6 mb-4'),
            
            _Div([
                _Columns(
                    _Html("Total Debits: $" . number_format($totalDebits, 2))
                        ->class('text-lg'),
                        
                    _Html("Total Credits: $" . number_format($totalCredits, 2))
                        ->class('text-lg'),
                        
                    _Html("Difference: $" . number_format($difference, 2))
                        ->class($isBalanced ? 'text-green-600 text-lg font-bold' : 'text-red-600 text-lg font-bold')
                ),
                
                _Html($isBalanced ? '✓ Transaction is balanced' : '✗ Transaction is not balanced')
                    ->class($isBalanced ? 'text-green-600 font-semibold mt-2' : 'text-red-600 font-semibold mt-2')
                    
            ])->class('p-4 border rounded ' . ($isBalanced ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'))
        ];
    }

    protected function addEntry()
    {
        $this->entries[] = [
            'account_id' => '',
            'line_description' => '',
            'debit_amount' => 0,
            'credit_amount' => 0
        ];
        return $this->refresh();
    }

    protected function removeEntry($index)
    {
        unset($this->entries[$index]);
        $this->entries = array_values($this->entries); // Re-index array
        return $this->refresh();
    }

    protected function clearCreditAmount($index)
    {
        if (request("entries.{$index}.debit_amount") > 0) {
            $this->entries[$index]['credit_amount'] = 0;
        }
        return $this->refresh();
    }

    protected function clearDebitAmount($index)
    {
        if (request("entries.{$index}.credit_amount") > 0) {
            $this->entries[$index]['debit_amount'] = 0;
        }
        return $this->refresh();
    }

    protected function validatePeriod()
    {
        $date = request('fiscal_date');
        if ($date) {
            $period = FiscalPeriod::getByDate($date);
            if (!$period || !$period->isOpenFor('GL')) {
                return _Html('Warning: Fiscal period is closed for GL transactions')
                    ->class('text-red-600 font-semibold mt-2');
            }
        }
        return $this->refresh();
    }

    public function beforeSave()
    {
        $this->model->transaction_type = GlTransaction::TYPE_MANUAL_GL;
        
        // Validate entries balance
        $totalDebits = collect(request('entries'))->sum('debit_amount');
        $totalCredits = collect(request('entries'))->sum('credit_amount');
        
        if (abs($totalDebits - $totalCredits) >= 0.01) {
            throw new \Exception('Transaction entries must balance (total debits must equal total credits)');
        }
    }

    public function afterSave()
    {
        // Delete existing entries if updating
        if ($this->model->wasRecentlyCreated === false) {
            $this->model->glEntries()->delete();
        }
        
        // Create new entries
        $entries = request('entries', []);
        foreach ($entries as $entryData) {
            if (empty($entryData['account_id'])) continue;
            
            $debitAmount = (float)($entryData['debit_amount'] ?? 0);
            $creditAmount = (float)($entryData['credit_amount'] ?? 0);
            
            if ($debitAmount == 0 && $creditAmount == 0) continue;
            
            $this->model->glEntries()->create([
                'account_id' => $entryData['account_id'],
                'line_description' => $entryData['line_description'] ?? null,
                'debit_amount' => $debitAmount,
                'credit_amount' => $creditAmount
            ]);
        }
    }

    public function rules()
    {
        return [
            'fiscal_date' => 'required|date',
            'transaction_description' => 'required|string|max:255',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|string|exists:fin_accounts,account_id',
            'entries.*.debit_amount' => 'nullable|numeric|min:0',
            'entries.*.credit_amount' => 'nullable|numeric|min:0',
            'entries.*.line_description' => 'nullable|string|max:255'
        ];
    }

    public function authorize()
    {
        return true; // Add proper authorization
    }
}
