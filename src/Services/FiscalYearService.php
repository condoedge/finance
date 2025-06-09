<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Models\FiscalYearSetup;
use Condoedge\Finance\Models\FiscalPeriod;
use Carbon\Carbon;

/**
 * Service for managing fiscal year setup and periods
 */
class FiscalYearService
{
    /**
     * Setup fiscal year for a team
     */
    public function setupFiscalYear(int $teamId, Carbon $startDate): FiscalYearSetup
    {
        // Deactivate any existing setup
        FiscalYearSetup::where('team_id', $teamId)->update(['is_active' => false]);
        
        // Create new fiscal year setup
        return FiscalYearSetup::create([
            'team_id' => $teamId,
            'company_fiscal_start_date' => $startDate,
            'is_active' => true,
        ]);
    }
    
    /**
     * Generate fiscal periods for a fiscal year
     */
    public function generateFiscalPeriods(int $teamId, int $fiscalYear, bool $regenerate = false): array
    {
        if ($regenerate) {
            FiscalPeriod::where('team_id', $teamId)
                        ->where('fiscal_year', $fiscalYear)
                        ->delete();
        }
        
        $fiscalSetup = FiscalYearSetup::getActiveForTeam($teamId);
        if (!$fiscalSetup) {
            throw new \Exception('No active fiscal year setup found for team.');
        }
        
        $periods = [];
        $startDate = $fiscalSetup->getFiscalYearStart($fiscalYear);
        
        for ($month = 1; $month <= 12; $month++) {
            $periodStart = $startDate->copy()->addMonths($month - 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            
            $periodId = sprintf('per%02d-%04d', $month, $fiscalYear);
            
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
        $fiscalSetup = FiscalYearSetup::getActiveForTeam($teamId);
        
        if (!$fiscalSetup) {
            return null;
        }
        
        return $fiscalSetup->getFiscalYear($date);
    }
    
    /**
     * Close fiscal period for specific modules
     */
    public function closePeriod(string $periodId, array $modules = ['GL', 'BNK', 'RM', 'PM']): FiscalPeriod
    {
        $period = FiscalPeriod::findOrFail($periodId);
        
        foreach ($modules as $module) {
            $field = 'is_open_' . strtolower($module);
            $period->$field = false;
        }
        
        $period->save();
        return $period;
    }
    
    /**
     * Open fiscal period for specific modules
     */
    public function openPeriod(string $periodId, array $modules = ['GL', 'BNK', 'RM', 'PM']): FiscalPeriod
    {
        $period = FiscalPeriod::findOrFail($periodId);
        
        foreach ($modules as $module) {
            $field = 'is_open_' . strtolower($module);
            $period->$field = true;
        }
        
        $period->save();
        return $period;
    }
    
    /**
     * Get all periods for a fiscal year
     */
    public function getPeriodsForFiscalYear(int $teamId, int $fiscalYear): \Illuminate\Database\Eloquent\Collection
    {
        return FiscalPeriod::where('team_id', $teamId)
                          ->where('fiscal_year', $fiscalYear)
                          ->orderBy('period_number')
                          ->get();
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
}
