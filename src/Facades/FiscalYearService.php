<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Fiscal Year Service Facade
 *
 * @method static \Condoedge\Finance\Models\FiscalYearSetup setupFiscalYear(int $teamId, \Carbon\Carbon $startDate)
 * @method static int calculateFiscalYear(\Carbon\Carbon $startDate)
 * @method static int|null getFiscalYearForDate(\Carbon\Carbon $date, int $teamId)
 * @method static \Condoedge\Finance\Models\FiscalPeriod createCustomPeriod(int $teamId, int $fiscalYear, string $periodCode, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate, string $description = null)
 * @method static \Condoedge\Finance\Models\FiscalPeriod closePeriod(string $periodId, array $modules)
 * @method static \Condoedge\Finance\Models\FiscalPeriod openPeriod(string $periodId, array $modules)
 * @method static \Condoedge\Finance\Models\FiscalPeriod|null getCurrentPeriod(int $teamId, \Carbon\Carbon $date = null)
 * @method static int|null getCurrentFiscalYear(int $teamId, \Carbon\Carbon $date = null)
 * @method static \Illuminate\Support\Collection getPeriodsForFiscalYear(int $teamId, int $fiscalYear)
 * @method static bool validateTransactionDate(\Carbon\Carbon $transactionDate, string $module, int $teamId)
 * @method static bool isFiscalYearClosed(int $teamId, int $fiscalYear, string $module = 'GL')
 * @method static array getFiscalYearSummary(int $teamId, int $fiscalYear)
 */
class FiscalYearService extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Condoedge\Finance\Services\FiscalYearService::class;
    }
}
