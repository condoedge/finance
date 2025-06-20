<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\FiscalPeriod;
use Carbon\Carbon;
use Condoedge\Finance\Enums\GlTransactionTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Enhanced Fiscal Year Service
 * 
 * Manages fiscal year setup, period generation, and period closing operations
 * following the requirement that fiscal year = calendar year + 1 for start dates
 */
class FiscalYearService
{
    /**
     * Setup fiscal year for a team
     */
    public function setupFiscalYear(int $teamId, Carbon $startDate): FiscalYearSetup
    {
        return DB::transaction(function () use ($teamId, $startDate) {
            // Deactivate any existing setup
            FiscalYearSetup::where('team_id', $teamId)->update(['is_active' => false]);
            
            // Create new fiscal year setup
            return FiscalYearSetup::create([
                'team_id' => $teamId,
                'company_fiscal_start_date' => $startDate,
                'is_active' => true,
            ]);
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
        
        $fiscalStartMonth = $fiscalSetup->company_fiscal_start_date->month;
        $fiscalStartDay = $fiscalSetup->company_fiscal_start_date->day;
        
        // Create fiscal start for the current year
        $currentYearFiscalStart = Carbon::create($date->year, $fiscalStartMonth, $fiscalStartDay);
        
        // If date is before fiscal start in current year, it belongs to previous fiscal year
        if ($date->lt($currentYearFiscalStart)) {
            return $date->year; // Previous fiscal year
        }
        
        return $date->year + 1; // Current fiscal year
    }
    
    /**
     * Generate fiscal periods for a fiscal year
     */
    public function generateFiscalPeriods(int $teamId, int $fiscalYear, bool $regenerate = false): array
    {
        return DB::transaction(function () use ($teamId, $fiscalYear, $regenerate) {
            if ($regenerate) {
                // Check if any periods are closed before deleting
                $closedPeriods = FiscalPeriod::where('team_id', $teamId)
                    ->where('fiscal_year', $fiscalYear)
                    ->where(function ($query) {
                        $query->where('is_open_gl', false)
                              ->orWhere('is_open_bnk', false)
                              ->orWhere('is_open_rm', false)
                              ->orWhere('is_open_pm', false);
                    })
                    ->count();
                    
                if ($closedPeriods > 0) {
                    throw new ValidationException('Cannot regenerate periods: some periods are already closed');
                }
                
                FiscalPeriod::where('team_id', $teamId)
                            ->where('fiscal_year', $fiscalYear)
                            ->delete();
            }
            
            $fiscalSetup = FiscalYearSetup::getActiveForTeam($teamId);
            if (!$fiscalSetup) {
                throw new \Exception('No active fiscal year setup found for team');
            }
            
            // Calculate the actual calendar year for this fiscal year
            $calendarYear = $fiscalYear - 1;
            $startDate = Carbon::create(
                $calendarYear, 
                $fiscalSetup->company_fiscal_start_date->month, 
                $fiscalSetup->company_fiscal_start_date->day
            );
            
            $periods = [];
            
            for ($month = 1; $month <= 12; $month++) {
                $periodStart = $startDate->copy()->addMonths($month - 1)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();
                
                // Create period ID in format: per01, per02, etc.
                $periodCode = sprintf('per%02d', $month);
                $periodId = "{$periodCode}-{$fiscalYear}";
                
                // Check if period already exists
                if (FiscalPeriod::where('period_id', $periodId)->exists()) {
                    continue;
                }
                
                $period = FiscalPeriod::create([
                    'period_id' => $periodId,
                    'team_id' => $teamId,
                    'fiscal_year' => $fiscalYear,
                    'period_number' => $month,
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'is_open_gl' => true,
                    'is_open_bnk' => true,
                    'is_open_rm' => true,
                    'is_open_pm' => true,
                ]);
                
                $periods[] = $period;
            }
            
            return $periods;
        });
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
            
            $periodId = "{$periodCode}-{$fiscalYear}";
            
            if (FiscalPeriod::where('period_id', $periodId)->exists()) {
                throw new ValidationException("Period {$periodId} already exists");
            }
            
            return FiscalPeriod::create([
                'period_id' => $periodId,
                'team_id' => $teamId,
                'fiscal_year' => $fiscalYear,
                'period_number' => 0, // Custom periods have 0
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_open_gl' => true,
                'is_open_bnk' => true,
                'is_open_rm' => true,
                'is_open_pm' => true,
            ]);
        });
    }
    
    /**
     * Close fiscal period for specific modules
     */
    public function closePeriod(string $periodId, array $modules): FiscalPeriod
    {
        return DB::transaction(function () use ($periodId, $modules) {
            $period = FiscalPeriod::findOrFail($periodId);
            
            $validModules = ['GL', 'BNK', 'RM', 'PM'];
            $modules = array_intersect($modules, $validModules);
            
            foreach ($modules as $module) {
                $field = 'is_open_' . strtolower($module);
                if (in_array($field, $period->getFillable())) {
                    $period->$field = false;
                }
            }
            
            $period->save();
            
            // Log the closing action (optional but recommended for audit)
            \Log::info("Fiscal period closed", [
                'period_id' => $periodId,
                'modules' => $modules,
                'closed_by' => auth()->id(),
                'closed_at' => now()
            ]);
            
            return $period->refresh();
        });
    }
    
    /**
     * Open fiscal period for specific modules
     */
    public function openPeriod(string $periodId, array $modules): FiscalPeriod
    {
        return DB::transaction(function () use ($periodId, $modules) {
            $period = FiscalPeriod::findOrFail($periodId);
            
            $validModules = ['GL', 'BNK', 'RM', 'PM'];
            $modules = array_intersect($modules, $validModules);
            
            foreach ($modules as $module) {
                $field = 'is_open_' . strtolower($module);
                if (in_array($field, $period->getFillable())) {
                    $period->$field = true;
                }
            }
            
            $period->save();
            
            // Log the opening action
            \Log::info("Fiscal period opened", [
                'period_id' => $periodId,
                'modules' => $modules,
                'opened_by' => auth()->id(),
                'opened_at' => now()
            ]);
            
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
                return [
                    'period' => $period,
                    'period_display' => "{$period->period_id} from {$period->start_date->format('Y-m-d')} to {$period->end_date->format('Y-m-d')}",
                    'status' => [
                        'GL' => $period->is_open_gl ? 'OPEN' : 'CLOSED',
                        'BNK' => $period->is_open_bnk ? 'OPEN' : 'CLOSED',
                        'RM' => $period->is_open_rm ? 'OPEN' : 'CLOSED',
                        'PM' => $period->is_open_pm ? 'OPEN' : 'CLOSED',
                    ]
                ];
            });
    }
    
    /**
     * Validate if a transaction can be posted to a specific date
     */
    public function validateTransactionDate(Carbon $transactionDate, GlTransactionTypeEnum $module, int $teamId): bool
    {
        $period = $this->getCurrentPeriod($teamId, $transactionDate);
        
        if (!$period) {
            throw new HttpException(
                403,
                __('finance-fiscal-year-no-period'),
            );
        }
        
        if (!$period->isOpenForModule($module)) {
            throw new HttpException(
                403,
                __('finance-fiscal-year-period-closed', ['module' => $module->label()]),
            );
        }
        
        return true;
    }
    
    /**
     * Check if all periods are closed for a fiscal year
     */
    public function isFiscalYearClosed(int $teamId, int $fiscalYear, string $module = 'GL'): bool
    {
        $field = 'is_open_' . strtolower($module);
        
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
            throw new \Exception('No fiscal year setup found');
        }
        
        $calendarYear = $fiscalYear - 1;
        $fiscalStart = Carbon::create(
            $calendarYear, 
            $fiscalSetup->company_fiscal_start_date->month, 
            $fiscalSetup->company_fiscal_start_date->day
        );
        $fiscalEnd = $fiscalStart->copy()->addYear()->subDay();
        
        return [
            'fiscal_year' => $fiscalYear,
            'fiscal_start_date' => $fiscalStart,
            'fiscal_end_date' => $fiscalEnd,
            'total_periods' => $periods->count(),
            'periods' => $periods,
            'closure_status' => [
                'GL' => $this->isFiscalYearClosed($teamId, $fiscalYear, 'GL'),
                'BNK' => $this->isFiscalYearClosed($teamId, $fiscalYear, 'BNK'),
                'RM' => $this->isFiscalYearClosed($teamId, $fiscalYear, 'RM'),
                'PM' => $this->isFiscalYearClosed($teamId, $fiscalYear, 'PM'),
            ]
        ];
    }
    
    /**
     * Validate period dates don't overlap
     */
    protected function validatePeriodDates(Carbon $startDate, Carbon $endDate, int $teamId, int $fiscalYear): void
    {
        if ($startDate->gte($endDate)) {
            throw new ValidationException('Start date must be before end date');
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
            throw new ValidationException('Period dates overlap with existing period');
        }
    }
}
