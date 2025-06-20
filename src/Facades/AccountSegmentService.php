<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Account Segment Service Facade
 * 
 * @method static void setupDefaultSegmentStructure()
 * @method static \Condoedge\Finance\Models\SegmentValue createSegmentValue(int $position, string $value, string $description)
 * @method static void setupSampleSegmentValues()
 * @method static \Condoedge\Finance\Models\GlAccount createAccount(array $segmentCodes, array $accountAttributes)
 * @method static \Condoedge\Finance\Models\GlAccount findOrCreateAccount(array $segmentCodes, array $accountAttributes)
 * @method static bool validateSegmentCombination(array $segmentCodes)
 * @method static array parseAccountId(string $accountId)
 * @method static string buildAccountId(array $segmentCodes)
 * @method static \Illuminate\Support\Collection getAvailableSegmentValues(int $position, bool $activeOnly = true)
 * @method static \Illuminate\Support\Collection getSegmentValueOptions(int $position)
 * @method static string getAccountDescription(array $segmentCodes)
 * @method static string getAccountFormatMask()
 * @method static \Illuminate\Support\Collection getSegmentDefinitions()
 * @method static array getSegmentValueUsage(int $segmentValueId)
 * @method static \Illuminate\Support\Collection searchAccountsBySegmentPattern(array $segmentPattern, int $teamId)
 * @method static \Illuminate\Support\Collection getAccountsWithSegmentValue(int $position, string $value, int $teamId)
 * @method static \Illuminate\Support\Collection bulkCreateAccounts(array $segmentCombinations, array $baseAttributes)
 * @method static array validateSegmentStructure()
 */
class AccountSegmentService extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Condoedge\Finance\Services\AccountSegmentService::class;
    }
}
