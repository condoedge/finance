<?php

namespace Condoedge\Finance\Services\Account;

use Condoedge\Finance\Models\Account;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\GlAccountSegment;
use Condoedge\Finance\Services\GlSegmentService;
use Condoedge\Finance\Casts\SafeDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * GL Account Service Implementation
 * 
 * Handles all account business logic including creation, validation,
 * balance calculations, hierarchy management, and usage statistics.
 * 
 * This implementation can be easily overridden by binding a custom 
 * implementation to the GlAccountServiceInterface in your service provider.
 */
class GlAccountService implements GlAccountServiceInterface
{
    protected GlSegmentService $segmentService;
    
    public function __construct(GlSegmentService $segmentService)
    {
        $this->segmentService = $segmentService;
    }
    
    /**
     * Create account with full validation
     */
    public function createAccount(array $attributes): Account
    {
        return DB::transaction(function () use ($attributes) {
            // Validate required attributes
            $this->validateAccountAttributes($attributes);
            
            // Validate account ID format and segments
            $this->validateAccountId($attributes['account_id'], $attributes['team_id']);
            
            // Check account doesn't already exist
            $this->validateAccountDoesNotExist($attributes['account_id'], $attributes['team_id']);
            
            // Auto-generate description if not provided
            if (empty($attributes['account_description'])) {
                $attributes['account_description'] = $this->generateAccountDescription(
                    $attributes['account_id'], 
                    $attributes['team_id']
                );
            }
            
            // Set default values
            $attributes = $this->setDefaultAccountAttributes($attributes);
            
            // Create account
            $account = Account::create($attributes);
            
            return $account->refresh();
        });
    }
    
    /**
     * Validate account ID
     */
    public function validateAccountId(string $accountId, int $teamId): bool
    {
        // Delegate to segment service for detailed validation
        $isValid = $this->segmentService->validateAccountId($accountId, $teamId);
        
        if (!$isValid) {
            throw new ValidationException("Invalid account ID format or segment values: {$accountId}");
        }
        
        return true;
    }
    
    /**
     * Get account balance
     */
    public function getAccountBalance(Account $account, ?Carbon $startDate = null, ?Carbon $endDate = null, bool $includeUnposted = false): SafeDecimal
    {
        $query = $account->glTransactionLines()
            ->whereHas('header', function($q) use ($startDate, $endDate, $includeUnposted) {
                if (!$includeUnposted) {
                    $q->where('is_posted', true);
                }
                if ($startDate) {
                    $q->where('fiscal_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('fiscal_date', '<=', $endDate);
                }
            });
        
        $debits = new SafeDecimal($query->sum('debit_amount') ?? '0.00');
        $credits = new SafeDecimal($query->sum('credit_amount') ?? '0.00');
        
        // Calculate balance based on account normal balance
        return $this->calculateNormalBalance($account, $debits, $credits);
    }
    
    /**
     * Get accounts by type
     */
    public function getAccountsByType(string $accountType, ?int $teamId = null, bool $activeOnly = true): Collection
    {
        $query = Account::byType($accountType)
            ->forTeam($teamId);
            
        if ($activeOnly) {
            $query->active();
        }
        
        return $query->orderBy('account_id')->get();
    }
    
    /**
     * Search accounts by pattern
     */
    public function searchAccountsByPattern(string $pattern, ?int $teamId = null): Collection
    {
        // Convert pattern to SQL LIKE pattern
        $sqlPattern = str_replace('*', '%', $pattern);
        
        return Account::forTeam($teamId)
            ->where('account_id', 'LIKE', $sqlPattern)
            ->orderBy('account_id')
            ->get();
    }
    
    /**
     * Get account hierarchy
     */
    public function getAccountHierarchy(?int $teamId = null): Collection
    {
        $accounts = Account::forTeam($teamId)
            ->active()
            ->orderBy('account_id')
            ->get();
        
        return $this->buildAccountHierarchy($accounts);
    }
    
    /**
     * Generate account ID from segments
     */
    public function generateAccountId(array $segments, int $teamId): string
    {
        // Validate segment count and values
        $this->validateSegments($segments, $teamId);
        
        // Delegate to segment service for formatting
        return $this->segmentService->formatAccountId($segments, $teamId);
    }
    
    /**
     * Get available segment values
     */
    public function getAvailableSegmentValues(int $segmentPosition, int $teamId): Collection
    {
        return $this->segmentService->getAvailableSegmentValues($segmentPosition, $teamId);
    }
    
    /**
     * Check if account can accept manual entries
     */
    public function canAcceptManualEntries(Account $account): bool
    {
        return $account->is_active && $account->allow_manual_entry;
    }
    
    /**
     * Get trial balance
     */
    public function getTrialBalance(?Carbon $startDate = null, ?Carbon $endDate = null, ?int $teamId = null): Collection
    {
        $accounts = Account::forTeam($teamId)
            ->active()
            ->orderBy('account_id')
            ->get();
        
        $trialBalance = collect();
        
        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account, $startDate, $endDate);
            
            // Only include accounts with non-zero balances
            if (!$balance->equals(new SafeDecimal('0.00'))) {
                $trialBalance->push([
                    'account_id' => $account->account_id,
                    'account_description' => $account->account_description,
                    'account_type' => $account->account_type,
                    'debit_balance' => $account->isNormalDebitAccount() && $balance->greaterThan(new SafeDecimal('0.00')) ? $balance : new SafeDecimal('0.00'),
                    'credit_balance' => $account->isNormalCreditAccount() && $balance->greaterThan(new SafeDecimal('0.00')) ? $balance : new SafeDecimal('0.00'),
                    'balance' => $balance,
                ]);
            }
        }
        
        return $trialBalance;
    }
    
    /**
     * Archive account
     */
    public function archiveAccount(Account $account, string $reason): Account
    {
        return DB::transaction(function () use ($account, $reason) {
            // Check if account has unposted transactions
            $this->validateCanArchiveAccount($account);
            
            // Mark as inactive
            $account->is_active = false;
            $account->archived_at = now();
            $account->archive_reason = $reason;
            $account->save();
            
            return $account;
        });
    }
    
    /**
     * Merge accounts
     */
    public function mergeAccounts(Account $fromAccount, Account $toAccount, string $reason): bool
    {
        return DB::transaction(function () use ($fromAccount, $toAccount, $reason) {
            // Validate merge is possible
            $this->validateAccountMerge($fromAccount, $toAccount);
            
            // Update all transaction lines to point to target account
            GlTransactionLine::where('account_id', $fromAccount->account_id)
                ->update(['account_id' => $toAccount->account_id]);
            
            // Archive the source account
            $this->archiveAccount($fromAccount, "Merged into {$toAccount->account_id}: {$reason}");
            
            return true;
        });
    }
    
    /**
     * Get account usage statistics
     */
    public function getAccountUsageStats(Account $account, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = $account->glTransactionLines()
            ->whereHas('header', function($q) use ($startDate, $endDate) {
                $q->where('is_posted', true);
                if ($startDate) {
                    $q->where('fiscal_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('fiscal_date', '<=', $endDate);
                }
            });
        
        $transactionCount = $query->count();
        $totalDebits = new SafeDecimal($query->sum('debit_amount') ?? '0.00');
        $totalCredits = new SafeDecimal($query->sum('credit_amount') ?? '0.00');
        $currentBalance = $this->getAccountBalance($account, $startDate, $endDate);
        
        return [
            'transaction_count' => $transactionCount,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'current_balance' => $currentBalance,
            'last_transaction_date' => $query->max('created_at'),
            'first_transaction_date' => $query->min('created_at'),
        ];
    }
    
    /* PROTECTED METHODS - Can be overridden for customization */
    
    /**
     * Validate account attributes
     */
    protected function validateAccountAttributes(array $attributes): void
    {
        $required = ['account_id', 'team_id', 'account_type'];
        
        foreach ($required as $field) {
            if (empty($attributes[$field])) {
                throw new ValidationException("Required field '{$field}' is missing");
            }
        }
        
        // Validate account type
        $validTypes = [
            Account::TYPE_ASSET,
            Account::TYPE_LIABILITY,
            Account::TYPE_EQUITY,
            Account::TYPE_REVENUE,
            Account::TYPE_EXPENSE,
        ];
        
        if (!in_array($attributes['account_type'], $validTypes)) {
            throw new ValidationException("Invalid account type: {$attributes['account_type']}");
        }
    }
    
    /**
     * Validate account doesn't exist
     */
    protected function validateAccountDoesNotExist(string $accountId, int $teamId): void
    {
        $exists = Account::where('account_id', $accountId)
            ->where('team_id', $teamId)
            ->exists();
            
        if ($exists) {
            throw new ValidationException("Account {$accountId} already exists");
        }
    }
    
    /**
     * Generate account description
     */
    protected function generateAccountDescription(string $accountId, int $teamId): string
    {
        return $this->segmentService->getAccountDescription($accountId, $teamId);
    }
    
    /**
     * Set default account attributes
     */
    protected function setDefaultAccountAttributes(array $attributes): array
    {
        $defaults = [
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => $attributes['team_id'] ?? currentTeamId(),
        ];
        
        return array_merge($defaults, $attributes);
    }
    
    /**
     * Calculate normal balance
     */
    protected function calculateNormalBalance(Account $account, SafeDecimal $debits, SafeDecimal $credits): SafeDecimal
    {
        if ($account->isNormalDebitAccount()) {
            return $debits->subtract($credits);
        } else {
            return $credits->subtract($debits);
        }
    }
    
    /**
     * Build account hierarchy from flat list
     */
    protected function buildAccountHierarchy(Collection $accounts): Collection
    {
        // Group by first segment (account type/category)
        return $accounts->groupBy(function ($account) {
            $segments = GlAccountSegment::parseAccountId($account->account_id);
            return $segments[0] ?? 'unknown';
        })->map(function ($groupAccounts, $firstSegment) {
            return [
                'segment' => $firstSegment,
                'accounts' => $groupAccounts,
                'count' => $groupAccounts->count(),
            ];
        });
    }
    
    /**
     * Validate segments
     */
    protected function validateSegments(array $segments, int $teamId): void
    {
        foreach ($segments as $position => $value) {
            $segmentNumber = $position + 1;
            
            if (!$this->segmentService->isValidSegmentValue($segmentNumber, $value, $teamId)) {
                throw new ValidationException("Invalid segment value '{$value}' for segment {$segmentNumber}");
            }
        }
    }
    
    /**
     * Validate can archive account
     */
    protected function validateCanArchiveAccount(Account $account): void
    {
        $hasUnpostedTransactions = $account->glTransactionLines()
            ->whereHas('header', function($q) {
                $q->where('is_posted', false);
            })
            ->exists();
            
        if ($hasUnpostedTransactions) {
            throw new ValidationException("Cannot archive account {$account->account_id}: has unposted transactions");
        }
    }
    
    /**
     * Validate account merge
     */
    protected function validateAccountMerge(Account $fromAccount, Account $toAccount): void
    {
        // Must be same account type
        if ($fromAccount->account_type !== $toAccount->account_type) {
            throw new ValidationException('Cannot merge accounts of different types');
        }
        
        // Both must be in same team
        if ($fromAccount->team_id !== $toAccount->team_id) {
            throw new ValidationException('Cannot merge accounts from different teams');
        }
        
        // Target account must be active
        if (!$toAccount->is_active) {
            throw new ValidationException('Target account must be active');
        }
    }
}
