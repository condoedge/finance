<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Account Segment Service Facade
 * 
 * @method static \Illuminate\Support\Collection getSegmentStructure()
 * @method static int getLastSegmentPosition()
 * @method static \Condoedge\Finance\Models\AccountSegment createOrUpdateSegment(\Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto $dto)
 * @method static \Condoedge\Finance\Models\SegmentValue createSegmentValue(\Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto $dto)
 * @method static \Condoedge\Finance\Models\GlAccount createAccount(\Condoedge\Finance\Models\Dto\Gl\CreateAccountDto $dto)
 * @method static \Condoedge\Finance\Models\GlAccount|null findAccountBySegmentValues(array $segmentValueIds, int $teamId)
 * @method static \Illuminate\Support\Collection searchAccountsByPattern(array $segmentValueIds, int $teamId)
 * @method static \Illuminate\Support\Collection getSegmentValues(int $segmentDefinitionId, bool $activeOnly = true)
 * @method static \Illuminate\Support\Collection getSegmentValuesGrouped(bool $activeOnly = true)
 * @method static array validateSegmentStructure()
 * @method static string getAccountFormatMask()
 * @method static string getAccountFormatExample()
 * @method static array getSegmentStatistics()
 * @method static \Illuminate\Support\Collection getSegmentsCoverageData()
 * 
 * @see \Condoedge\Finance\Services\AccountSegmentServiceInterface
 */
class AccountSegmentService extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Condoedge\Finance\Services\AccountSegmentServiceInterface::class;
    }
}
