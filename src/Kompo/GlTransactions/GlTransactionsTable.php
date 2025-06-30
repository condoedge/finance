<?php

namespace Condoedge\Finance\Kompo\GlTransactions;

use Condoedge\Finance\Models\GlTransactionHeader;

use Condoedge\Finance\Services\GlTransactionServiceInterface;
use Condoedge\Utils\Kompo\Common\Table;

class GlTransactionsTable extends Table
{
    protected $teamId;
    
    public function created()
    {
        $this->teamId = currentTeamId();
    }
    
    public function query()
    {
        return GlTransactionHeader::with(['lines', 'fiscalPeriod', 'customer'])
            ->forTeam($this->teamId)
            ->latest('fiscal_date')
            ->latest('gl_transaction_number');
    }
    
    public function top()
    {
        return _FlexBetween(
            _TitleMain('translate.finance-gl-transactions'),
            _Link('translate.finance-create-transaction')->button()
                ->icon('plus')
                ->href(route('finance.gl.gl-transaction-form'))
                ->class('btn-primary')
        );
    }
    
    public function headers()
    {
        return [
            _Th('translate.finance-transaction-id')->sort('gl_transaction_id'),
            _Th('translate.finance-date')->sort('fiscal_date'),
            _Th('translate.finance-description'),
            _Th('translate.finance-type'),
            _Th('translate.finance-debits')->class('text-right'),
            _Th('translate.finance-credits')->class('text-right'),
            _Th('translate.finance-status')->class('text-center'),
            _Th('translate.finance-actions')->class('text-center'),
        ];
    }
    
    public function render($transaction)
    {
        return _TableRow(
            // Transaction ID
            _Html($transaction->gl_transaction_id)
                ->class('font-mono text-sm'),
            
            // Fiscal Date
            _Html($transaction->fiscal_date->format('Y-m-d')),
            
            // Description
            _Html($transaction->transaction_description)
                ->class('max-w-xs truncate'),
            
            // Type
            $this->renderTransactionType($transaction),
            
            // Total Debits
            _Html(number_format($transaction->total_debits, 2))
                ->class('text-right font-mono'),
            
            // Total Credits
            _Html(number_format($transaction->total_credits, 2))
                ->class('text-right font-mono'),
            
            // Status
            $this->renderStatus($transaction),
            
            // Actions
            $this->renderActions($transaction)
        );
    }
    
    /**
     * Render transaction type badge
     */
    protected function renderTransactionType($transaction)
    {
        $types = [
            GlTransactionHeader::TYPE_MANUAL_GL => ['label' => 'translate.finance-manual-gl', 'color' => 'info'],
            GlTransactionHeader::TYPE_BANK => ['label' => 'translate.finance-bank', 'color' => 'primary'],
            GlTransactionHeader::TYPE_RECEIVABLE => ['label' => 'translate.finance-receivable', 'color' => 'success'],
            GlTransactionHeader::TYPE_PAYABLE => ['label' => 'translate.finance-payable', 'color' => 'warning'],
        ];
        
        $type = $types[$transaction->gl_transaction_type] ?? ['label' => 'translate.finance-unknown-type', 'color' => 'secondary'];
        
        return _Pill($type['label'])->class("bg-{$type['color']}");
    }
    
    /**
     * Render transaction status
     */
    protected function renderStatus($transaction)
    {
        return _Flex(
            // Balance status
            $transaction->is_balanced ?
                _Icon('check-circle')->class('text-success') :
                _Icon('alert-circle')->class('text-danger'),
            
            // Posted status
            $transaction->is_posted ?
                _Pill('translate.finance-posted')->class('bg-success text-white') :
                _Pill('translate.finance-draft')->class('bg-gray-200')
        )->class('gap-2 justify-center');
    }
    
    /**
     * Render action buttons
     */
    protected function renderActions($transaction)
    {
        return _Flex(
            // View/Edit
            _Link()
                ->icon($transaction->is_posted ? 'eye' : 'pencil')
                ->href(route('finance.gl.gl-transaction-form', $transaction->gl_transaction_id))
                ->class('text-primary'),
            
            // Post button (if not posted and balanced)
            !$transaction->is_posted && $transaction->is_balanced ?
                _Link()
                    ->icon('check')
                    ->selfPost('postTransaction', ['transaction_id' => $transaction->gl_transaction_id])
                    ->inAlert('translate.finance-confirm-post-transaction')
                    ->class('text-success') : null,
            
            // Reverse button (if posted)
            $transaction->is_posted ?
                _Link()
                    ->icon('rotate-ccw')
                    ->selfGet('getReverseModal', ['transaction_id' => $transaction->gl_transaction_id])
                    ->inModal()
                    ->class('text-warning') : null,
            
            // Delete button (if not posted)
            !$transaction->is_posted ?
                _DeleteLink()
                    ->byKey($transaction)
                    ->class('text-danger') : null
        )->class('gap-2 justify-center');
    }
    
    /**
     * Post a transaction
     */
    public function postTransaction(GlTransactionServiceInterface $glTransactionService)
    {
        try {
            $transactionId = request('transaction_id');
            $transaction = GlTransactionHeader::where('gl_transaction_id', $transactionId)
                ->forTeam($this->teamId)
                ->firstOrFail();
            
            $glTransactionService->postTransaction($transaction);
            
            return _Alert('translate.finance-transaction-posted-successfully')
                ->icon('check')
                ->success()
                ->refresh();
                
        } catch (\Exception $e) {
            return _Alert($e->getMessage())->error();
        }
    }
    
    /**
     * Get reverse transaction modal
     */
    public function getReverseModal()
    {
        $transactionId = request('transaction_id');
        $transaction = GlTransactionHeader::where('gl_transaction_id', $transactionId)
            ->forTeam($this->teamId)
            ->firstOrFail();
        
        return _Modal(
            _Html('translate.finance-reverse-transaction')->class('text-xl font-bold mb-4'),
            
            _Alert('translate.finance-reverse-transaction-warning')->warning()->class('mb-4'),
            
            _Rows(
                _Html('translate.finance-original-transaction: ' . $transaction->gl_transaction_id)
                    ->class('mb-2'),
                _Html('translate.finance-description: ' . $transaction->transaction_description)
                    ->class('mb-4'),
                
                _Textarea('translate.finance-reversal-reason')
                    ->name('reversal_description')
                    ->placeholder('translate.finance-enter-reversal-reason')
                    ->required()
                    ->rows(3),
            ),
            
            _FlexEnd(
                _Button('translate.finance-cancel')
                    ->outlined()
                    ->closeModal(),
                _Button('translate.finance-confirm-reverse')
                    ->class('bg-warning')
                    ->selfPost('reverseTransaction', ['transaction_id' => $transactionId])
                    ->withAllFormValues()
                    ->inAlert()
            )->class('gap-3 mt-4')
        );
    }
    
    /**
     * Reverse a transaction
     */
    public function reverseTransaction(GlTransactionServiceInterface $glTransactionService)
    {
        try {
            $transactionId = request('transaction_id');
            $reversalDescription = request('reversal_description');
            
            $transaction = GlTransactionHeader::where('gl_transaction_id', $transactionId)
                ->forTeam($this->teamId)
                ->firstOrFail();
            
            $reversalTransaction = $glTransactionService->reverseTransaction(
                $transaction->gl_transaction_id,
                $reversalDescription
            );
            
            return _Alert('translate.finance-transaction-reversed-successfully')
                ->icon('check')
                ->success()
                ->refresh()
                ->closeModal();
                
        } catch (\Exception $e) {
            return _Alert($e->getMessage())->error();
        }
    }
    
    public function filters()
    {
        return [
            _Columns(
                _DateRange('translate.finance-date-range')
                    ->name('date_range')
                    ->filterColumn('fiscal_date'),
                    
                _Select('translate.finance-transaction-type')
                    ->name('transaction_type')
                    ->options([
                        '' => 'translate.finance-all-types',
                        1 => 'translate.finance-manual-gl',
                        2 => 'translate.finance-bank',
                        3 => 'translate.finance-receivable',
                        4 => 'translate.finance-payable',
                    ])
                    ->filterColumn('gl_transaction_type'),
                    
                _Select('translate.finance-status')
                    ->name('status')
                    ->options([
                        '' => 'translate.finance-all-status',
                        'posted' => 'translate.finance-posted',
                        'draft' => 'translate.finance-draft',
                        'balanced' => 'translate.finance-balanced',
                        'unbalanced' => 'translate.finance-unbalanced',
                    ])
                    ->filter(function($query, $value) {
                        switch($value) {
                            case 'posted':
                                $query->posted();
                                break;
                            case 'draft':
                                $query->unposted();
                                break;
                            case 'balanced':
                                $query->balanced();
                                break;
                            case 'unbalanced':
                                $query->unbalanced();
                                break;
                        }
                    }),
                    
                _Input('translate.finance-search')
                    ->name('search')
                    ->placeholder('translate.finance-search-by-id-or-description')
                    ->filter(function($query, $value) {
                        $query->where(function($q) use ($value) {
                            $q->where('gl_transaction_id', 'like', "%{$value}%")
                              ->orWhere('transaction_description', 'like', "%{$value}%");
                        });
                    }),
            )->class('gap-4')
        ];
    }
}
