<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Dto\Gl\CreateGlTransactionDto;
use Condoedge\Finance\Casts\SafeDecimal;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Condoedge\Finance\Facades\FiscalYearService;
use Condoedge\Finance\Models\SegmentValue;

class GlTransactionService implements GlTransactionServiceInterface
{
    protected function createTransactionHeader(CreateGlTransactionDto $dto): GlTransactionHeader
    {
        $fiscalPeriod = FiscalYearService::getOrCreatePeriodForDate($dto->team_id, carbon($dto->fiscal_date));

        $header = new GlTransactionHeader;
        // $header->gl_transaction_number = $dto->gl_transaction_number; // Defined in trigger
        $header->fiscal_date = $dto->fiscal_date;
        $header->fiscal_period_id = $fiscalPeriod->id;
        $header->gl_transaction_type = $dto->gl_transaction_type;
        $header->transaction_description = $dto->transaction_description ?? '';
        $header->team_id = $dto->team_id;
        $header->save();

        return $header;
    }
    /**
     * Create a complete GL transaction with lines
     */
    public function createTransaction(CreateGlTransactionDto $dto): GlTransactionHeader
    {
        return DB::transaction(function() use ($dto) {
            // Create header
            $header = $this->createTransactionHeader($dto);

            // Create lines
            $lines = $dto->getLinesDtos(); // Convert to DTOs if they're arrays
            foreach ($lines as $lineDto) {
                // Validate account allows manual entry
                $naturalAccount = $lineDto->account_id ? GlAccount::where('account_id', $lineDto->account_id)
                    ->forTeam()
                    ->first()
                    ->getLastSegmentValue()
                    : SegmentValue::find($lineDto->natural_account_id);

                if (!$naturalAccount->is_active) {
                    throw new \Exception(__("translate.account-is-inactive", ['account_id' => $naturalAccount->id]));
                }

                $glTransactionLine = new GlTransactionLine;
                $glTransactionLine->gl_transaction_id = $header->id;
                $glTransactionLine->account_id = $lineDto->account_id ?? GlAccount::getFromLatestSegmentValue($lineDto->natural_account_id)->id;
                $glTransactionLine->line_description = $lineDto->line_description;
                $glTransactionLine->debit_amount = $lineDto->debit_amount->toFloat();
                $glTransactionLine->credit_amount = $lineDto->credit_amount->toFloat();
                $glTransactionLine->team_id = $dto->team_id;
                $glTransactionLine->save();
            }
            
            // Refresh to get updated balance status from triggers
            $header->refresh();
            
            if (!$header->is_balanced) {
                throw new \Exception(__('translate.transaction-not-balanced-after-creation'));
            }
            
            return $header;
        });
    }
    
    /**
     * Create a transaction line with validation
     */
    public function createTransactionLine(array $lineData): GlTransactionLine
    {
        // Validate account exists and is usable
        $account = GlAccount::find($lineData['account_id']);
        if (!$account) {
            throw new \Exception(__("translate.account-not-found", ['account_id' => $lineData['account_id']]));
        }
        
        if (!$account->is_active) {
            throw new \Exception(__("translate.account-is-inactive", ['account_id' => $lineData['account_id']]));
        }
        
        // Validate amounts
        $debitAmount = new SafeDecimal($lineData['debit_amount'] ?? 0);
        $creditAmount = new SafeDecimal($lineData['credit_amount'] ?? 0);
        
        if ($debitAmount->greaterThan(0) && $creditAmount->greaterThan(0)) {
            throw new \Exception(__('translate.line-cannot-have-both-debit-and-credit'));
        }
          if ($debitAmount->equals(0) && $creditAmount->equals(0)) {
            throw new \Exception(__('translate.line-must-have-either-debit-or-credit'));
        }
        
        return GlTransactionLine::create($lineData);
    }
    
    /**
     * Validate that lines balance
     */
    protected function validateLinesBalance(array $lines): void
    {
        $totalDebits = new SafeDecimal(0);
        $totalCredits = new SafeDecimal(0);
        
        foreach ($lines as $line) {
            $debit = new SafeDecimal($line['debit_amount'] ?? 0);
            $credit = new SafeDecimal($line['credit_amount'] ?? 0);
            
            $totalDebits = $totalDebits->add($debit);
            $totalCredits = $totalCredits->add($credit);
        }
          if (!$totalDebits->equals($totalCredits)) {
            throw new \Exception(
                __("translate.transaction-not-balanced-with-amounts", [
                    'debits' => finance_currency($totalDebits),
                    'credits' => finance_currency($totalCredits),
                ])
            );
        }
    }
    
    /**
     * Post a transaction (make it final)
     */
    public function postTransaction($transaction): GlTransactionHeader
    {
        if (is_string($transaction)) {
            $transaction = GlTransactionHeader::where('id', $transaction)
                ->forTeam()
                ->firstOrFail();
        }
        
        if ($transaction->is_posted) {
            throw new \Exception(__('translate.transaction-already-posted'));
        }
        
        if (!$transaction->is_balanced) {
            throw new \Exception(__('translate.cannot-post-unbalanced-transaction'));
        }
        
        if (!$transaction->canBeModified()) {
            throw new \Exception(__('translate.transaction-cannot-be-modified'));
        }
        
        $transaction->post();
        
        return $transaction;
    }
    
    /**
     * Reverse a posted transaction
     */
    public function reverseTransaction(string $transactionId, string $reversalDescription = null): GlTransactionHeader
    {
        $originalTransaction = GlTransactionHeader::findOrFail($transactionId);
        
        if (!$originalTransaction->is_posted) {
            throw new \Exception(__('translate.cannot-reverse-unposted-transaction'));
        }
        
        return DB::transaction(function() use ($originalTransaction, $reversalDescription) {
            // Create reversal header
            $reversalData = [
                'fiscal_date' => now()->format('Y-m-d'),
                'gl_transaction_type' => $originalTransaction->gl_transaction_type,
                'transaction_description' => $reversalDescription ?? 
                    __("translate.reversal-of-transaction", ['transaction_id' => $originalTransaction->gl_transaction_id]),
                'customer_id' => $originalTransaction->customer_id,
                'vendor_id' => $originalTransaction->vendor_id,
                'team_id' => $originalTransaction->team_id,
            ];
            
            $reversalHeader = GlTransactionHeader::createTransaction($reversalData);
            
            // Create reversed lines (swap debits and credits)
            foreach ($originalTransaction->lines as $originalLine) {
                $transactionLine = new GlTransactionLine;
                $transactionLine->gl_transaction_id = $reversalHeader->id;
                $transactionLine->account_id = $originalLine->account_id;
                $transactionLine->line_description = __("translate.reversal-line", ['description' => $originalLine->line_description ?? '']);
                $transactionLine->debit_amount = $originalLine->credit_amount;
                $transactionLine->credit_amount = $originalLine->debit_amount;
                $transactionLine->save();
            }
            
            // Post the reversal automatically
            $reversalHeader->refresh();
            $reversalHeader->post();
            
            return $reversalHeader;
        });
    }
    
    /**
     * Get account balance for a date range
     */
    public function getAccountBalance(
        string $accountId, 
        \Carbon\Carbon $startDate = null, 
        \Carbon\Carbon $endDate = null,
        bool $postedOnly = true
    ): SafeDecimal {
        $account = GlAccount::findOrFail($accountId);
        
        $query = GlTransactionLine::where('account_id', $accountId)
            ->whereHas('header', function($q) use ($startDate, $endDate, $postedOnly) {
                if ($postedOnly) {
                    $q->where('is_posted', true);
                }
                if ($startDate) {
                    $q->where('fiscal_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('fiscal_date', '<=', $endDate);
                }
            });
        
        $debits = new SafeDecimal($query->sum('debit_amount'));
        $credits = new SafeDecimal($query->sum('credit_amount'));
        
        // Return natural balance based on account type
        if ($account->isNormalDebitAccount()) {
            return $debits->subtract($credits);
        } else {
            return $credits->subtract($debits);
        }
    }
    
    /**
     * Get trial balance for a period
     */
    public function getTrialBalance(
        \Carbon\Carbon $startDate, 
        \Carbon\Carbon $endDate,
        bool $postedOnly = true
    ): array {
        $accounts = GlAccount::active()->get();
        $trialBalance = [];
        
        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance(
                $account->account_id, 
                $startDate, 
                $endDate, 
                $postedOnly
            );
            
            if (!$balance->equals(0)) {
                $trialBalance[] = [
                    'account_id' => $account->account_id,
                    'account_description' => $account->account_description,
                    'account_type' => $account->account_type,
                    'balance' => $balance,
                    'debit_balance' => $account->isNormalDebitAccount() && $balance->greaterThan(0) ? $balance : new SafeDecimal(0),
                    'credit_balance' => $account->isNormalCreditAccount() && $balance->greaterThan(0) ? $balance : new SafeDecimal(0),
                ];
            }
        }
        
        return $trialBalance;
    }
    
    /**
     * Generate recurring journal entries
     */
    public function createRecurringEntry(
        array $templateData, 
        array $templateLines, 
        \Carbon\Carbon $effectiveDate,
        string $description = null
    ): GlTransactionHeader {
        $headerData = array_merge($templateData, [
            'fiscal_date' => $effectiveDate->format('Y-m-d'),
            'transaction_description' => $description ?? $templateData['transaction_description'],
        ]);
        
        return $this->createTransaction($headerData, $templateLines);
    }
    
    /**
     * Close fiscal period for GL
     */
    public function closeFiscalPeriod(string $periodId): void
    {
        $period = FiscalPeriod::findOrFail($periodId);
        
        // Verify all transactions in period are posted
        $unpostedCount = GlTransactionHeader::where('fiscal_period', $periodId)
            ->where('is_posted', false)
            ->count();
            
        if ($unpostedCount > 0) {
            throw new \Exception(__("translate.cannot-close-period-unposted-transactions", ['count' => $unpostedCount]));
        }
        
        // Close the period for GL
        $period->closeForModule('gl');
    }
    
    /**
     * Open fiscal period for GL
     */
    public function openFiscalPeriod(string $periodId): void
    {
        $period = FiscalPeriod::findOrFail($periodId);
        $period->openForModule('gl');
    }
}
