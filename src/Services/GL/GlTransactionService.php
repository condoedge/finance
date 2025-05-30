<?php

namespace Condoedge\Finance\Services\GL;

use Condoedge\Finance\Models\GL\GlTransaction;
use Condoedge\Finance\Models\GL\GlEntry;
use Condoedge\Finance\Models\GL\GlAccount;
use Condoedge\Finance\Models\GL\FiscalPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GlTransactionService
{
    /**
     * Create manual GL transaction
     */
    public function createManualGlTransaction(
        string $description,
        Carbon $fiscalDate,
        array $entries,
        array $additionalData = []
    ): GlTransaction {
        return DB::transaction(function() use ($description, $fiscalDate, $entries, $additionalData) {
            // Validate entries
            $this->validateEntries($entries);
            
            // Check if period is open
            $this->validatePeriodOpen($fiscalDate, GlTransaction::TYPE_MANUAL_GL);
            
            // Create transaction
            $transaction = GlTransaction::createManualTransaction(
                $description,
                $fiscalDate,
                $entries,
                $additionalData
            );

            return $transaction;
        });
    }

    /**
     * Create system GL transaction (from other modules)
     */
    public function createSystemGlTransaction(
        int $transactionType,
        string $description,
        Carbon $fiscalDate,
        array $entries,
        array $additionalData = []
    ): GlTransaction {
        return DB::transaction(function() use ($transactionType, $description, $fiscalDate, $entries, $additionalData) {
            // Validate entries
            $this->validateEntries($entries);
            
            // Check if period is open for this transaction type
            $this->validatePeriodOpen($fiscalDate, $transactionType);
            
            // Create transaction
            $transaction = GlTransaction::create(array_merge([
                'fiscal_date' => $fiscalDate,
                'transaction_type' => $transactionType,
                'transaction_description' => $description,
            ], $additionalData));

            // Create entries
            foreach ($entries as $entry) {
                $transaction->glEntries()->create($entry);
            }

            return $transaction;
        });
    }

    /**
     * Validate transaction entries
     */
    protected function validateEntries(array $entries): void
    {
        if (empty($entries)) {
            throw new \Exception('Transaction must have at least one entry');
        }

        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($entries as $entry) {
            // Validate required fields
            if (!isset($entry['account_id'])) {
                throw new \Exception('Each entry must have an account_id');
            }

            if (!isset($entry['debit_amount']) && !isset($entry['credit_amount'])) {
                throw new \Exception('Each entry must have either debit_amount or credit_amount');
            }

            $debitAmount = $entry['debit_amount'] ?? 0;
            $creditAmount = $entry['credit_amount'] ?? 0;

            if ($debitAmount < 0 || $creditAmount < 0) {
                throw new \Exception('Debit and credit amounts must be positive');
            }

            if ($debitAmount > 0 && $creditAmount > 0) {
                throw new \Exception('Entry cannot have both debit and credit amounts');
            }

            if ($debitAmount == 0 && $creditAmount == 0) {
                throw new \Exception('Entry must have either debit or credit amount');
            }

            // Validate account exists and is active
            $account = GlAccount::where('account_id', $entry['account_id'])->first();
            if (!$account) {
                throw new \Exception("Account {$entry['account_id']} does not exist");
            }

            if (!$account->is_active) {
                throw new \Exception("Account {$entry['account_id']} is not active");
            }

            $totalDebits += $debitAmount;
            $totalCredits += $creditAmount;
        }

        // Check if debits equal credits
        if (bccomp($totalDebits, $totalCredits, config('kompo-finance.decimal-scale')) !== 0) {
            throw new \Exception('Total debits must equal total credits');
        }
    }

    /**
     * Validate period is open
     */
    protected function validatePeriodOpen(Carbon $fiscalDate, int $transactionType): void
    {
        $period = FiscalPeriod::getByDate($fiscalDate);
        
        if (!$period) {
            throw new \Exception('No fiscal period found for date ' . $fiscalDate->format('Y-m-d'));
        }

        $moduleType = match($transactionType) {
            GlTransaction::TYPE_MANUAL_GL => 'GL',
            GlTransaction::TYPE_BANK => 'BNK',
            GlTransaction::TYPE_RECEIVABLE => 'RM',
            GlTransaction::TYPE_PAYABLE => 'PM',
            default => 'GL'
        };

        if (!$period->isOpenFor($moduleType)) {
            throw new \Exception("Fiscal period {$period->period_id} is closed for {$moduleType} transactions");
        }
    }

    /**
     * Update GL transaction
     */
    public function updateGlTransaction(int $transactionId, array $data): GlTransaction
    {
        return DB::transaction(function() use ($transactionId, $data) {
            $transaction = GlTransaction::findOrFail($transactionId);
            
            // Check if period is still open
            if (!$transaction->isPeriodOpen()) {
                throw new \Exception('Cannot modify transaction in closed period');
            }

            // Update transaction header
            if (isset($data['transaction_description'])) {
                $transaction->transaction_description = $data['transaction_description'];
            }

            if (isset($data['fiscal_date'])) {
                $this->validatePeriodOpen(Carbon::parse($data['fiscal_date']), $transaction->transaction_type);
                $transaction->fiscal_date = $data['fiscal_date'];
            }

            $transaction->save();

            // Update entries if provided
            if (isset($data['entries'])) {
                $this->validateEntries($data['entries']);
                
                // Delete existing entries
                $transaction->glEntries()->delete();
                
                // Create new entries
                foreach ($data['entries'] as $entry) {
                    $transaction->glEntries()->create($entry);
                }
            }

            return $transaction->fresh();
        });
    }

    /**
     * Delete GL transaction
     */
    public function deleteGlTransaction(int $transactionId): bool
    {
        return DB::transaction(function() use ($transactionId) {
            $transaction = GlTransaction::findOrFail($transactionId);
            
            // Check if period is still open
            if (!$transaction->isPeriodOpen()) {
                throw new \Exception('Cannot delete transaction in closed period');
            }

            // Delete entries first
            $transaction->glEntries()->delete();
            
            // Delete transaction
            return $transaction->delete();
        });
    }

    /**
     * Get GL transactions with filters
     */
    public function getGlTransactions(array $filters = []): array
    {
        $query = GlTransaction::with(['glEntries.glAccount', 'fiscalPeriod']);

        // Apply filters
        if (isset($filters['fiscal_date_from'])) {
            $query->where('fiscal_date', '>=', $filters['fiscal_date_from']);
        }

        if (isset($filters['fiscal_date_to'])) {
            $query->where('fiscal_date', '<=', $filters['fiscal_date_to']);
        }

        if (isset($filters['fiscal_year'])) {
            $query->where('fiscal_year', $filters['fiscal_year']);
        }

        if (isset($filters['fiscal_period'])) {
            $query->where('fiscal_period', $filters['fiscal_period']);
        }

        if (isset($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (isset($filters['account_id'])) {
            $query->whereHas('glEntries', function($q) use ($filters) {
                $q->where('account_id', $filters['account_id']);
            });
        }

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        $transactions = $query->orderBy('gl_transaction_number', 'desc')->get();

        return $transactions->map(function($transaction) {
            return [
                'id' => $transaction->id,
                'gl_transaction_number' => $transaction->gl_transaction_number,
                'fiscal_date' => $transaction->fiscal_date,
                'fiscal_year' => $transaction->fiscal_year,
                'fiscal_period' => $transaction->fiscal_period,
                'transaction_type' => $transaction->transaction_type,
                'transaction_type_name' => $transaction->getTransactionTypeName(),
                'description' => $transaction->transaction_description,
                'total_amount' => $transaction->getTotal(),
                'is_balanced' => $transaction->validateBalance(),
                'created_by' => $transaction->created_by,
                'created_at' => $transaction->created_at,
                'entries' => $transaction->glEntries->map(function($entry) {
                    return [
                        'id' => $entry->id,
                        'account_id' => $entry->account_id,
                        'account_description' => $entry->glAccount->account_description ?? 'Unknown Account',
                        'line_description' => $entry->line_description,
                        'debit_amount' => $entry->debit_amount,
                        'credit_amount' => $entry->credit_amount,
                        'entry_type' => $entry->getEntryType()
                    ];
                })
            ];
        })->toArray();
    }

    /**
     * Get GL transaction by ID
     */
    public function getGlTransaction(int $transactionId): array
    {
        $transaction = GlTransaction::with(['glEntries.glAccount', 'fiscalPeriod', 'customer', 'vendor'])
                                  ->findOrFail($transactionId);

        return [
            'id' => $transaction->id,
            'gl_transaction_number' => $transaction->gl_transaction_number,
            'fiscal_date' => $transaction->fiscal_date,
            'fiscal_year' => $transaction->fiscal_year,
            'fiscal_period' => $transaction->fiscal_period,
            'transaction_type' => $transaction->transaction_type,
            'transaction_type_name' => $transaction->getTransactionTypeName(),
            'description' => $transaction->transaction_description,
            'originating_module_transaction_id' => $transaction->originating_module_transaction_id,
            'customer' => $transaction->customer ? [
                'id' => $transaction->customer->id,
                'name' => $transaction->customer->name
            ] : null,
            'vendor' => $transaction->vendor ? [
                'id' => $transaction->vendor->id,
                'name' => $transaction->vendor->vendor_name
            ] : null,
            'total_amount' => $transaction->getTotal(),
            'is_balanced' => $transaction->validateBalance(),
            'period_open' => $transaction->isPeriodOpen(),
            'created_by' => $transaction->created_by,
            'created_at' => $transaction->created_at,
            'modified_by' => $transaction->modified_by,
            'modified_at' => $transaction->modified_at,
            'entries' => $transaction->glEntries->map(function($entry) {
                return [
                    'id' => $entry->id,
                    'account_id' => $entry->account_id,
                    'account_description' => $entry->glAccount->account_description ?? 'Unknown Account',
                    'account_type' => $entry->glAccount->account_type ?? null,
                    'line_description' => $entry->line_description,
                    'debit_amount' => $entry->debit_amount,
                    'credit_amount' => $entry->credit_amount,
                    'entry_type' => $entry->getEntryType()
                ];
            })
        ];
    }

    /**
     * Reverse GL transaction
     */
    public function reverseGlTransaction(int $transactionId, string $reversalReason): GlTransaction
    {
        return DB::transaction(function() use ($transactionId, $reversalReason) {
            $originalTransaction = GlTransaction::with('glEntries')->findOrFail($transactionId);
            
            // Create reversal entries (flip debits and credits)
            $reversalEntries = [];
            foreach ($originalTransaction->glEntries as $entry) {
                $reversalEntries[] = [
                    'account_id' => $entry->account_id,
                    'line_description' => "Reversal: " . ($entry->line_description ?? ''),
                    'debit_amount' => $entry->credit_amount, // Flip
                    'credit_amount' => $entry->debit_amount  // Flip
                ];
            }

            // Create reversal transaction
            $reversalTransaction = $this->createManualGlTransaction(
                "REVERSAL: " . $originalTransaction->transaction_description . " - " . $reversalReason,
                $originalTransaction->fiscal_date,
                $reversalEntries,
                [
                    'originating_module_transaction_id' => $originalTransaction->id,
                    'customer_id' => $originalTransaction->customer_id,
                    'vendor_id' => $originalTransaction->vendor_id,
                    'team_id' => $originalTransaction->team_id
                ]
            );

            return $reversalTransaction;
        });
    }
}
