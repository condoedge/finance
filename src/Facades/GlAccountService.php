<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * GL Account Service Facade
 * 
 * @method static \Condoedge\Finance\Models\GlAccount createAccount(array $attributes)
 * @method static bool validateAccountId(string $accountId, int $teamId)
 * @method static \Condoedge\Finance\Casts\SafeDecimal getAccountBalance(\Condoedge\Finance\Models\GlAccount $account, \Carbon\Carbon|null $startDate = null, \Carbon\Carbon|null $endDate = null, bool $includeUnposted = false)
 * @method static \Illuminate\Support\Collection getAccountsByType(string $accountType, int|null $teamId = null, bool $activeOnly = true)
 * @method static \Illuminate\Support\Collection searchAccountsByPattern(string $pattern, int|null $teamId = null)
 * @method static \Illuminate\Support\Collection getAccountHierarchy(int|null $teamId = null)
 * @method static string generateAccountId(array $segments, int $teamId)
 * @method static \Illuminate\Support\Collection getAvailableSegmentValues(int $segmentPosition, int $teamId)
 * @method static bool canAcceptManualEntries(\Condoedge\Finance\Models\GlAccount $account)
 * @method static \Illuminate\Support\Collection getTrialBalance(\Carbon\Carbon|null $startDate = null, \Carbon\Carbon|null $endDate = null, int|null $teamId = null)
 * @method static \Condoedge\Finance\Models\GlAccount archiveAccount(\Condoedge\Finance\Models\GlAccount $account, string $reason)
 * @method static bool mergeAccounts(\Condoedge\Finance\Models\GlAccount $fromAccount, \Condoedge\Finance\Models\GlAccount $toAccount, string $reason)
 * @method static array getAccountUsageStats(\Condoedge\Finance\Models\GlAccount $account, \Carbon\Carbon|null $startDate = null, \Carbon\Carbon|null $endDate = null)
 * 
 * @see \Condoedge\Finance\Services\Account\GlAccountServiceInterface
 */
class GlAccountService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Condoedge\Finance\Services\Account\GlAccountServiceInterface::class;
    }
}
