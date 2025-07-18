<?php

namespace Condoedge\Finance\Services;

use Carbon\Carbon;
use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\FiscalYearSetup;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Enhanced Fiscal Year Service
 *
 * Manages fiscal year setup, period generation, and period closing operations
 * following the requirement that fiscal year = calendar year + 1 for start dates
 */
class FiscalYearService
{
    /**
     * Setup fiscal year for a team and auto-create periods up to current date
     */
    public function setupFiscalYear(int $teamId, Carbon $startDate): FiscalYearSetup
    {
        return DB::transaction(function () use ($teamId, $startDate) {
            $fiscalSetup = FiscalYearSetup::forTeam($teamId)->first();

            if (!$fiscalSetup) {
                $fiscalSetup = new FiscalYearSetup();
                $fiscalSetup->team_id = $teamId;
            }

            $fiscalSetup->fiscal_start_date = $startDate;
            $fiscalSetup->save();

            // Remove unnecessary periods
            $this->cleanPeriodsOutOfFiscalSetup($teamId, $startDate);

            // Auto-create periods from start date to current date
            $this->createPeriodsUpToDate($teamId, $startDate, now());

            return $fiscalSetup;
        });
    }

    /**
     * Calculate fiscal year from start date
     * Rule: If fiscal start is 2024-05-01, fiscal year is 2025
     */
    public function calculateFiscalYear(Carbon $startDate): int
    {
        return $startDate->year + 1;
    }

    /**
     * Get fiscal year for any given date based on fiscal setup
     */
    public function getFiscalYearForDate(Carbon $date, int $teamId): ?int
    {
        $fiscalSetup = FiscalYearSetup::getActiveForTeam($teamId);
        if (!$fiscalSetup) {
            return null;
        }

        $fiscalStartMonth = $fiscalSetup->fiscal_start_date->month;
        $fiscalStartDay = $fiscalSetup->fiscal_start_date->day;

        // Create fiscal start for the current year
        $currentYearFiscalStart = Carbon::create($date->year, $fiscalStartMonth, $fiscalStartDay);

        // If date is before fiscal start in current year, it belongs to previous fiscal year
        if ($date->lt($currentYearFiscalStart)) {
            return $date->year; // Previous fiscal year
        }

        return $date->year + 1; // Current fiscal year
    }

    /**
     * Generate custom period (for non-monthly periods)
     */
    public function createCustomPeriod(
        int $teamId,
        int $fiscalYear,
        string $periodCode,
        Carbon $startDate,
        Carbon $endDate,
        string $description = null
    ): FiscalPeriod {
        return DB::transaction(function () use ($teamId, $fiscalYear, $periodCode, $startDate, $endDate, $description) {
            $this->validatePeriodDates($startDate, $endDate, $teamId, $fiscalYear);


            if (FiscalPeriod::where('team_id', $teamId)
                ->where('fiscal_year', $fiscalYear)
                ->where('period_number', 0) // Custom periods have 0
                ->exists()) {
                throw new Exception(__('error-period-for-fiscal-year-already-exists', [
                    'fiscal_year' => $fiscalYear,
                    'team_id' => $teamId
                ]));
            }

            $period = new FiscalPeriod();
            $period->team_id = $teamId;
            $period->fiscal_year = $fiscalYear;
            $period->period_number = 0; // Custom periods have 0
            $period->start_date = $startDate;
            $period->end_date = $endDate;
            $period->is_open_gl = true;
            $period->is_open_bnk = true;
            $period->is_open_rm = true;
            $period->is_open_pm = true;
            $period->save();

            return $period;
        });
    }

    /**
     * Close fiscal period for specific modules
     *
     * @param string $periodId Period ID
     * @param array $modules Array of module codes (GL, BNK, RM, PM) or GlTransactionTypeEnum instances
     */
    public function closePeriod(string $periodId, array $modules): FiscalPeriod
    {
        return DB::transaction(function () use ($periodId, $modules) {
            $period = FiscalPeriod::findOrFail($periodId);

            // Convert modules to enums
            $enums = [];
            foreach ($modules as $module) {
                if ($module instanceof GlTransactionTypeEnum) {
                    $enums[] = $module;
                } elseif (is_string($module)) {
                    $enum = GlTransactionTypeEnum::from($module);
                    if ($enum) {
                        $enums[] = $enum;
                    }
                }
            }

            // Close periods for each enum
            foreach ($enums as $enum) {
                $field = $enum->getFiscalPeriodOpenField();
                $period->$field = false;
            }

            $period->save();

            return $period->refresh();
        });
    }

    /**
     * Open fiscal period for specific modules
     *
     * @param string $periodId Period ID
     * @param array $modules Array of module codes (GL, BNK, RM, PM) or GlTransactionTypeEnum instances
     */
    public function openPeriod(string $periodId, array $modules): FiscalPeriod
    {
        return DB::transaction(function () use ($periodId, $modules) {
            $period = FiscalPeriod::findOrFail($periodId);

            // Convert modules to enums
            $enums = [];
            foreach ($modules as $module) {
                if ($module instanceof GlTransactionTypeEnum) {
                    $enums[] = $module;
                } elseif (is_string($module)) {
                    $enum = GlTransactionTypeEnum::from($module);
                    if ($enum) {
                        $enums[] = $enum;
                    }
                }
            }

            // Open periods for each enum
            foreach ($enums as $enum) {
                $field = $enum->getFiscalPeriodOpenField();
                $period->$field = true;
            }

            $period->save();

            return $period->refresh();
        });
    }

    /**
     * Get current fiscal period for a team
     */
    public function getCurrentPeriod(int $teamId, Carbon $date = null): ?FiscalPeriod
    {
        $date = $date ?? now();
        return FiscalPeriod::getPeriodFromDate($date, $teamId);
    }

    public function closeExpiredPeriods(int $teamId): void
    {
        $expiredPeriods = FiscalPeriod::where('team_id', $teamId)
            ->where('end_date', '<', now()->startOfMonth())
            ->get();

        foreach ($expiredPeriods as $period) {
            $this->closePeriod($period->id, GlTransactionTypeEnum::cases());
        }
    }


    /**
     * Get or create period for a specific date
     *
     * @param int $teamId
     * @param Carbon $date
     * @param bool $onlyCurrentMonth If true, only create periods for current month
     *
     * @return FiscalPeriod
     *
     * @throws Exception
     */
    public function getOrCreatePeriodForDate(int $teamId, Carbon $date, bool $onlyCurrentMonth = true): FiscalPeriod
    {
        $fiscalYear = $this->getFiscalYearForDate($date, $teamId);

        if (!$fiscalYear) {
            throw new Exception(__('error-fiscal-year-not-found'));
        }

        // Try to get existing period
        $period = FiscalPeriod::getPeriodFromDate($date, $teamId);

        if (!$period) {
            // Check if we should create the period
            if ($onlyCurrentMonth && !$date->isSameMonth(now())) {
                throw new InvalidArgumentException(
                    __('error-with-values-period-does-not-exist-just-can-create-for-current-month', [
                        'date' => $date->format('Y-m-d')
                    ])
                );
            }

            // Create a new period for the month
            $periodNumber = $this->calculatePeriodNumber($date, $teamId);
            $periodCode = sprintf('per%02d', $periodNumber);
            $periodId = "{$periodCode}-{$fiscalYear}";

            // Check if period already exists (race condition protection)
            $period = FiscalPeriod::lockForUpdate()->find($periodId);
            if ($period) {
                return $period;
            }

            // Create using individual property assignment
            $period = new FiscalPeriod();
            $period->team_id = $teamId;
            $period->fiscal_year = $fiscalYear;
            $period->period_number = $periodNumber;
            $period->start_date = $date->copy()->startOfMonth();
            $period->end_date = $date->copy()->endOfMonth();
            $period->is_open_gl = true;
            $period->is_open_bnk = true;
            $period->is_open_rm = true;
            $period->is_open_pm = true;
            $period->save();
        }

        return $period;
    }

    /**
     * Get current fiscal year for a team
     */
    public function getCurrentFiscalYear(int $teamId, Carbon $date = null): ?int
    {
        $date = $date ?? now();
        return $this->getFiscalYearForDate($date, $teamId);
    }

    /**
     * Get all periods for a fiscal year with status summary
     */
    public function getPeriodsForFiscalYear(int $teamId, int $fiscalYear): \Illuminate\Support\Collection
    {
        return FiscalPeriod::where('team_id', $teamId)
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('period_number')
            ->get()
            ->map(function ($period) {
                $status = [];
                foreach (GlTransactionTypeEnum::cases() as $enum) {
                    $status[$enum->moduleCode()] = $period->isOpenForModule($enum) ? 'OPEN' : 'CLOSED';
                }

                return [
                    'period' => $period,
                    'period_display' => "per{$period->id} from {$period->start_date->format('Y-m-d')} to {$period->end_date->format('Y-m-d')}",
                    'status' => $status
                ];
            });
    }

    /**
     * Validate if a transaction can be posted to a specific date
     * Will auto-create period for current month if it doesn't exist
     */
    public function validateTransactionDate(Carbon $transactionDate, GlTransactionTypeEnum $module, int $teamId): bool
    {
        // Try to get or create period (only for current month)
        $period = $this->getOrCreatePeriodForDate($teamId, $transactionDate, true);

        if (!$period->isOpenForModule($module)) {
            throw new InvalidArgumentException(
                __('finance-fiscal-year-period-closed', ['module' => $module->label()]),
            );
        }

        return true;
    }

    /**
     * Check if all periods are closed for a fiscal year
     *
     * @param int $teamId Team ID
     * @param int $fiscalYear Fiscal year
     * @param string|GlTransactionTypeEnum $module Module code or enum
     */
    public function isFiscalYearClosed(int $teamId, int $fiscalYear, string|GlTransactionTypeEnum $module = 'GL'): bool
    {
        // Convert to enum if string
        if (is_string($module)) {
            $enum = GlTransactionTypeEnum::fromModuleCode($module);
            if (!$enum) {
                throw new InvalidArgumentException(
                    __('error-with-values-invalid-module-code', ['module' => $module])
                );
            }
        } else {
            $enum = $module;
        }

        $field = $enum->getFiscalPeriodOpenField();

        $openPeriodsCount = FiscalPeriod::where('team_id', $teamId)
            ->where('fiscal_year', $fiscalYear)
            ->where($field, true)
            ->count();

        return $openPeriodsCount === 0;
    }

    /**
     * Get fiscal year summary
     */
    public function getFiscalYearSummary(int $teamId, int $fiscalYear): array
    {
        $periods = $this->getPeriodsForFiscalYear($teamId, $fiscalYear);
        $fiscalSetup = FiscalYearSetup::getActiveForTeam($teamId);

        if (!$fiscalSetup) {
            throw new Exception(__('error-fiscal-year-setup-not-found'));
        }

        $calendarYear = $fiscalYear - 1;
        $fiscalStart = Carbon::create(
            $calendarYear,
            $fiscalSetup->fiscal_start_date->month,
            $fiscalSetup->fiscal_start_date->day
        );
        $fiscalEnd = $fiscalStart->copy()->addYear()->subDay();

        return [
            'fiscal_year' => $fiscalYear,
            'fiscal_start_date' => $fiscalStart,
            'fiscal_end_date' => $fiscalEnd,
            'total_periods' => $periods->count(),
            'periods' => $periods,
            'closure_status' => array_combine(
                array_map(fn ($e) => $e->moduleCode(), GlTransactionTypeEnum::cases()),
                array_map(fn ($e) => $this->isFiscalYearClosed($teamId, $fiscalYear, $e), GlTransactionTypeEnum::cases())
            )
        ];
    }

    /**
     * Calculate period number for a given date
     */
    public function calculatePeriodNumber(Carbon $date, int $teamId): int
    {
        $fiscalSetup = FiscalYearSetup::getActiveForTeam($teamId);
        if (!$fiscalSetup) {
            throw new Exception(__('error-fiscal-year-setup-not-found'));
        }

        $fiscalStartMonth = $fiscalSetup->fiscal_start_date->month;
        $fiscalStartDay = $fiscalSetup->fiscal_start_date->day;

        // Calculate months from fiscal year start
        $currentYearFiscalStart = Carbon::create($date->year, $fiscalStartMonth, $fiscalStartDay);
        if ($date->lt($currentYearFiscalStart)) {
            // Date is in previous fiscal year
            $currentYearFiscalStart->subYear();
        }

        $monthsFromStart = $currentYearFiscalStart->diffInMonths($date) + 1;

        return min(max($monthsFromStart, 1), 12); // Ensure between 1-12
    }

    /**
     * Pre-create periods for upcoming month
     * Should be run via cron job before month starts
     *
     * @param int $daysAhead Days to look ahead
     * @param bool $closePrevious Whether to close previous periods
     *
     * @return array Results of the operation
     */
    public function preCreateUpcomingPeriods(int $daysAhead = 1, bool $closePrevious = false)
    {
        $upcomingDate = now()->addDays($daysAhead)->startOfMonth();

        // Get all teams with fiscal year setup
        $teams = FiscalYearSetup::query()
            ->select('team_id')
            ->distinct()
            ->pluck('team_id');

        foreach ($teams as $teamId) {
            try {
                // Closing previous periods if required
                if ($closePrevious) {
                    $previousMonth = $upcomingDate->copy()->subMonth();
                    $previousPeriod = $this->getCurrentPeriod($teamId, $previousMonth);
                    if ($previousPeriod && $previousPeriod->is_open_gl) {
                        $this->closePeriod($previousPeriod->id, GlTransactionTypeEnum::cases());
                    }
                }

                $this->getOrCreatePeriodForDate($teamId, $upcomingDate, false);
            } catch (Exception $e) {
                Log::error("Failed to pre-create period for team {$teamId} on {$upcomingDate->format('Y-m-d')}: " . $e->getMessage());
            }
        }
    }

    /**
     * Validate period dates don't overlap
     */
    protected function validatePeriodDates(Carbon $startDate, Carbon $endDate, int $teamId, int $fiscalYear): void
    {
        if ($startDate->gte($endDate)) {
            throw new InvalidArgumentException(__('error-start-date-must-be-before-end-date'));
        }

        // Check for overlapping periods
        $overlapping = FiscalPeriod::where('team_id', $teamId)
            ->where('fiscal_year', $fiscalYear)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->exists();

        if ($overlapping) {
            throw new InvalidArgumentException(__('error-period-dates-overlap'));
        }
    }

    /**
     * Create all periods from fiscal start date up to a specific date
     */
    public function createPeriodsUpToDate(int $teamId, Carbon $fiscalStartDate, Carbon $upToDate): array
    {
        $periods = [];
        $currentDate = $fiscalStartDate->copy()->startOfMonth();
        $endDate = $upToDate->copy()->endOfMonth();

        while ($currentDate->lte($endDate)) {
            try {
                // Get fiscal year for this month
                $fiscalYear = $this->getFiscalYearForDate($currentDate, $teamId);
                if (!$fiscalYear) {
                    throw new Exception(__('error-fiscal-year-not-found'));
                }

                // Calculate period number
                $periodNumber = $this->calculatePeriodNumber($currentDate, $teamId);

                // Check if period already exists
                if (!FiscalPeriod::where('team_id', $teamId)
                    ->where('fiscal_year', $fiscalYear)
                    ->where('period_number', $periodNumber)
                    ->exists()
                ) {
                    $period = new FiscalPeriod();
                    $period->team_id = $teamId;
                    $period->fiscal_year = $fiscalYear;
                    $period->period_number = $periodNumber;
                    $period->start_date = $currentDate->copy()->startOfMonth();
                    $period->end_date = $currentDate->copy()->endOfMonth();
                    $period->is_open_gl = now()->isSameMonth($currentDate);
                    $period->is_open_bnk = now()->isSameMonth($currentDate);
                    $period->is_open_rm = now()->isSameMonth($currentDate);
                    $period->is_open_pm = now()->isSameMonth($currentDate);
                    $period->save();

                    $periods[] = $period;
                }

                // Move to next month
                $currentDate->addMonth();

            } catch (Exception $e) {
                // Continue with next month
                $currentDate->addMonth();
            }
        }

        return $periods;
    }

    public function cleanPeriodsOutOfFiscalSetup(int $teamId, Carbon $fiscalStartDate): void
    {
        // Get all periods for the team
        $periods = FiscalPeriod::where('team_id', $teamId)
            ->where('start_date', '<', $fiscalStartDate)
            ->get();

        foreach ($periods as $period) {
            // Delete each period
            $period->forceDelete();
        }
    }
}
