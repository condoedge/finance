<?php

namespace Condoedge\Finance\Services\Account;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\GlTransactionHeader;
use Condoedge\Finance\Models\GlTransactionLine;
use Condoedge\Finance\Models\AccountSegmentAssignment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Services\AccountSegmentService;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountFromSegmentsDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * GL Account Service Implementation - Updated for Segment-Based Architecture
 * 
 * Handles all account business logic using the new segment assignment system where:
 * - Accounts are created by combining reusable segment values
 * - Segment assignments are stored in fin_account_segment_assignments
 * - Account IDs are computed from segment combinations (e.g., "10-03-4000")
 */
class GlAccountService implements GlAccountServiceInterface
{
    protected AccountSegmentService $segmentService;
    
    public function __construct(AccountSegmentService $segmentService)
    {
        $this->segmentService = $segmentService;
    }
    
    /**
     * Create account with full validation using segment system
     */
    public function createAccount(array $attributes): GlAccount
    {
        return DB::transaction(function () use ($attributes) {
            // Validate required attributes
            $this->validateAccountAttributes($attributes);
            
            // If account_id is provided, parse segments and use segment creation
            if (isset($attributes['account_id'])) {
                $segmentCodes = $this->segmentService->parseAccountId($attributes['account_id']);
                return $this->createAccountFromSegments($segmentCodes, $attributes);
            }
            
            throw new ValidationException('account_id must be provided or use createAccountFromSegments method');
        });
    }
    
    /**
     * Create account from segment codes
     */
    public function createAccountFromSegments(array $segmentCodes, array $attributes): GlAccount
    {
        return DB::transaction(function () use ($segmentCodes, $attributes) {
            // Validate segment combination
            $this->validateSegmentCombination($segmentCodes);
            
            // Check account doesn't already exist
            $accountId = $this->segmentService->buildAccountId($segmentCodes);
            $this->validateAccountDoesNotExist($accountId, $attributes['team_id']);
            
            // Auto-generate description if not provided
            if (empty($attributes['account_description'])) {
                $attributes['account_description'] = $this->segmentService->getAccountDescription($segmentCodes);
            }
            
            // Set default values
            $attributes = $this->setDefaultAccountAttributes($attributes);
            
            // Create account using the segment service
            return $this->segmentService->createAccount($segmentCodes, $attributes);
        });
    }
    
    /**
     * Create account from DTO
     */
    public function createAccountFromDto(CreateAccountFromSegmentsDto $dto): GlAccount
    {
        return $this->createAccountFromSegments($dto->segmentCodes, $dto->toAccountAttributes());
    }
    
    /**
     * Find or create account from segment codes
     */
    public function findOrCreateAccountFromSegments(array $segmentCodes, array $attributes): GlAccount
    {
        return $this->segmentService->findOrCreateAccount($segmentCodes, $attributes);
    }
    
    /**
     * Validate account ID using segment system
     */
    public function validateAccountId(string $accountId, int $teamId): bool
    {
        $segmentCodes = $this->segmentService->parseAccountId($accountId);
        $isValid = $this->segmentService->validateSegmentCombination($segmentCodes);
        
        if (!$isValid) {
            throw new ValidationException("Invalid account ID format or segment values: {$accountId}");
        }
        
        return true;
    }
    
    /**
     * Get account balance
     */
    public function getAccountBalance(GlAccount $account, ?Carbon $startDate = null, ?Carbon $endDate = null, bool $includeUnposted = false): SafeDecimal
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
        $query = GlAccount::byType($accountType)
            ->forTeam($teamId);
            
        if ($activeOnly) {
            $query->active();
        }
        
        return $query->orderBy('account_id')->get();
    }
    
    /**
     * Search accounts by segment pattern
     * Example: searchAccountsBySegmentPattern(['*', '03', '*'], 1) finds all accounts with team '03'
     */
    public function searchAccountsBySegmentPattern(array $segmentPattern, ?int $teamId = null): Collection
    {
        $teamId = $teamId ?? currentTeamId();
        return $this->segmentService->searchAccountsBySegmentPattern($segmentPattern, $teamId);
    }
    
    /**
     * Search accounts by pattern (supports wildcards)
     */
    public function searchAccountsByPattern(string $pattern, ?int $teamId = null): Collection
    {
        // Convert pattern to SQL LIKE pattern
        $sqlPattern = str_replace('*', '%', $pattern);
        
        return GlAccount::forTeam($teamId)
            ->where('account_id', 'LIKE', $sqlPattern)
            ->orderBy('account_id')
            ->get();
    }
    
    /**
     * Get account hierarchy grouped by segment values
     */
    public function getAccountHierarchy(?int $teamId = null): Collection
    {
        $accounts = GlAccount::forTeam($teamId)
            ->active()
            ->orderBy('account_id')
            ->get();
        
        return $this->buildAccountHierarchyBySegments($accounts);
    }
    
    /**
     * Get accounts that share specific segment values
     */
    public function getAccountsWithSegmentValue(int $position, string $value, ?int $teamId = null): Collection
    {
        $teamId = $teamId ?? currentTeamId();
        return $this->segmentService->getAccountsWithSegmentValue($position, $value, $teamId);
    }
    
    /**
     * Generate account ID from segments
     */
    public function generateAccountId(array $segments, int $teamId): string
    {
        // Validate segment combination
        $this->validateSegmentCombination($segments);
        
        return $this->segmentService->buildAccountId($segments);
    }
    
    /**
     * Get available segment values for a position
     */
    public function getAvailableSegmentValues(int $segmentPosition, int $teamId): Collection
    {
        return $this->segmentService->getAvailableSegmentValues($segmentPosition);
    }
    
    /**
     * Get segment value options for dropdown
     */
    public function getSegmentValueOptions(int $position): Collection
    {
        return $this->segmentService->getSegmentValueOptions($position);
    }
    
    /**
     * Check if account can accept manual entries
     */
    public function canAcceptManualEntries(GlAccount $account): bool
    {
        return $account->is_active && $account->allow_manual_entry;
    }
    
    /**
     * Get trial balance
     */
    public function getTrialBalance(?Carbon $startDate = null, ?Carbon $endDate = null, ?int $teamId = null): Collection
    {
        $accounts = GlAccount::forTeam($teamId)
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
                    'segment_details' => $account->segment_details,
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
    public function archiveAccount(GlAccount $account, string $reason): GlAccount
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
    public function mergeAccounts(GlAccount $fromAccount, GlAccount $toAccount, string $reason): bool
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
    public function getAccountUsageStats(GlAccount $account, ?Carbon $startDate = null, ?Carbon $endDate = null): array
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
            'segment_details' => $account->segment_details,
            'last_transaction_date' => $query->max('created_at'),
            'first_transaction_date' => $query->min('created_at'),
        ];
    }
    
    /**
     * Bulk create accounts from segment combinations
     */
    public function bulkCreateAccountsFromSegments(array $segmentCombinations, array $baseAttributes): Collection
    {
        return $this->segmentService->bulkCreateAccounts($segmentCombinations, $baseAttributes);
    }
    
    /**
     * Get account format mask
     */
    public function getAccountFormatMask(): string
    {
        return $this->segmentService->getAccountFormatMask();
    }
    
    /* PROTECTED METHODS - Can be overridden for customization */
    
    /**
     * Validate account attributes
     */
    protected function validateAccountAttributes(array $attributes): void
    {
        $required = ['account_type', 'team_id'];
        
        foreach ($required as $field) {
            if (empty($attributes[$field])) {
                throw new ValidationException("Required field '{$field}' is missing");
            }
        }
        
        // Validate account type
        $validTypes = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE'];
        
        if (!in_array($attributes['account_type'], $validTypes)) {
            throw new ValidationException("Invalid account type: {$attributes['account_type']}");
        }
    }
    
    /**
     * Validate segment combination
     */
    protected function validateSegmentCombination(array $segmentCodes): void
    {
        if (!$this->segmentService->validateSegmentCombination($segmentCodes)) {
            throw new ValidationException('Invalid segment combination: ' . implode('-', $segmentCodes));
        }
    }
    
    /**
     * Validate account doesn't exist
     */
    protected function validateAccountDoesNotExist(string $accountId, int $teamId): void
    {
        $exists = GlAccount::where('account_id', $accountId)
            ->where('team_id', $teamId)
            ->exists();
            
        if ($exists) {
            throw new ValidationException("Account {$accountId} already exists");
        }
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
    protected function calculateNormalBalance(GlAccount $account, SafeDecimal $debits, SafeDecimal $credits): SafeDecimal
    {
        if ($account->isNormalDebitAccount()) {
            return $debits->subtract($credits);
        } else {
            return $credits->subtract($debits);
        }
    }
    
    /**
     * Build account hierarchy grouped by segment values
     */
    protected function buildAccountHierarchyBySegments(Collection $accounts): Collection
    {
        // Group by first segment (typically parent team or account type)
        return $accounts->groupBy(function ($account) {
            $segmentCodes = $this->segmentService->parseAccountId($account->account_id);
            return $segmentCodes[1] ?? 'unknown'; // First segment
        })->map(function ($groupAccounts, $firstSegment) {
            // Get segment value description
            $segmentValue = SegmentValue::findByPositionAndValue(1, $firstSegment);
            $segmentDescription = $segmentValue ? $segmentValue->segment_description : $firstSegment;
            
            return [
                'segment_value' => $firstSegment,
                'segment_description' => $segmentDescription,
                'accounts' => $groupAccounts,
                'count' => $groupAccounts->count(),
            ];
        });
    }
    
    /**
     * Validate can archive account
     */
    protected function validateCanArchiveAccount(GlAccount $account): void
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
    protected function validateAccountMerge(GlAccount $fromAccount, GlAccount $toAccount): void
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
