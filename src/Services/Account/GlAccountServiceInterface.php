<?php

namespace Condoedge\Finance\Services\Account;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Casts\SafeDecimal;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Interface for GL Account Service
 * 
 * This interface allows easy override of account business logic
 * by implementing this interface in external packages or custom services.
 */
interface GlAccountServiceInterface
{
    /**
     * Create account with full validation
     * 
     * @param array $attributes Account attributes including account_id, team_id
     * @return GlAccount
     * @throws \Exception When validation fails or account already exists
     */
    public function createAccount(array $attributes): GlAccount;
    
    /**
     * Validate account ID format and segment values
     * 
     * @param string $accountId
     * @param int $teamId
     * @return bool
     * @throws \Exception When validation fails with detailed message
     */
    public function validateAccountId(string $accountId, int $teamId): bool;
    
    /**
     * Get account balance for date range
     * 
     * @param GlAccount $account
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @param bool $includeUnposted Include unposted transactions
     * @return SafeDecimal
     */
    public function getAccountBalance(GlAccount $account, ?Carbon $startDate = null, ?Carbon $endDate = null, bool $includeUnposted = false): SafeDecimal;
    
    /**
     * Get accounts by type for team
     * 
     * @param string $accountType Account::TYPE_* constants
     * @param int|null $teamId
     * @param bool $activeOnly
     * @return Collection<GlAccount>
     */
    public function getAccountsByType(string $accountType, ?int $teamId = null, bool $activeOnly = true): Collection;
    
    /**
     * Search accounts by account ID pattern
     * 
     * @param string $pattern Account ID pattern (e.g., "10-***-****")
     * @param int|null $teamId
     * @return Collection<GlAccount>
     */
    public function searchAccountsByPattern(string $pattern, ?int $teamId = null): Collection;
    
    /**
     * Get account hierarchy (parent-child relationships based on segments)
     * 
     * @param int|null $teamId
     * @return Collection Nested collection representing account hierarchy
     */
    public function getAccountHierarchy(?int $teamId = null): Collection;
    
    /**
     * Generate account ID from segments
     * 
     * @param array $segments Array of segment values
     * @param int $teamId
     * @return string Formatted account ID
     * @throws \Exception When segments are invalid
     */
    public function generateAccountId(array $segments, int $teamId): string;
    
    /**
     * Get available segment values for specific segment position
     * 
     * @param int $segmentPosition 1-based position
     * @param int $teamId
     * @return Collection<object> Collection with value and description
     */
    public function getAvailableSegmentValues(int $segmentPosition, int $teamId): Collection;
    
    /**
     * Check if account can accept manual journal entries
     * 
     * @param GlAccount $account
     * @return bool
     */
    public function canAcceptManualEntries(GlAccount $account): bool;
    
    /**
     * Get trial balance for date range
     * 
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @param int|null $teamId
     * @return Collection Trial balance data with debits/credits
     */
    public function getTrialBalance(?Carbon $startDate = null, ?Carbon $endDate = null, ?int $teamId = null): Collection;
    
    /**
     * Archive/deactivate account
     * 
     * @param GlAccount $account
     * @param string $reason
     * @return GlAccount
     * @throws \Exception When account cannot be archived (has active transactions)
     */
    public function archiveAccount(GlAccount $account, string $reason): GlAccount;
    
    /**
     * Merge account into another account
     * 
     * @param GlAccount $fromAccount
     * @param GlAccount $toAccount
     * @param string $reason
     * @return bool Success status
     * @throws \Exception When accounts cannot be merged
     */
    public function mergeAccounts(GlAccount $fromAccount, GlAccount $toAccount, string $reason): bool;
    
    /**
     * Get account usage statistics
     * 
     * @param GlAccount $account
     * @param Carbon|null $startDate
     * @param Carbon|null $endDate
     * @return array Statistics including transaction count, balance history
     */
    public function getAccountUsageStats(GlAccount $account, ?Carbon $startDate = null, ?Carbon $endDate = null): array;
}
