<?php

namespace Condoedge\Finance\Services\GL;

use Condoedge\Finance\Models\GL\FiscalYearSetup;
use Condoedge\Finance\Models\GL\FiscalPeriod;
use Carbon\Carbon;

class FiscalPeriodService
{
    /**
     * Create fiscal periods for a fiscal year
     */
    public function createFiscalPeriods(int $fiscalYear, Carbon $fiscalStartDate, int $numberOfPeriods = 12): array
    {
        $periods = [];
        $currentDate = $fiscalStartDate->copy();

        for ($i = 1; $i <= $numberOfPeriods; $i++) {
            $periodId = 'per' . str_pad($i, 2, '0', STR_PAD_LEFT);
            
            $startDate = $currentDate->copy();
            $endDate = $currentDate->copy()->endOfMonth();
            
            // Ensure we don't exceed the fiscal year
            if ($i === $numberOfPeriods) {
                $endDate = $fiscalStartDate->copy()->addYear()->subDay();
            }

            $period = FiscalPeriod::updateOrCreate(
                ['period_id' => $periodId],
                [
                    'fiscal_year' => $fiscalYear,
                    'period_number' => $i,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_open_gl' => true,
                    'is_open_bnk' => true,
                    'is_open_rm' => true,
                    'is_open_pm' => true
                ]
            );

            $periods[] = $period;
            $currentDate->addMonth();
        }

        return $periods;
    }

    /**
     * Close period for specific modules
     */
    public function closePeriod(string $periodId, array $modules = ['GL', 'BNK', 'RM', 'PM']): bool
    {
        $period = FiscalPeriod::find($periodId);
        
        if (!$period) {
            throw new \Exception("Period {$periodId} not found");
        }

        foreach ($modules as $module) {
            $period->closeFor($module);
        }

        return true;
    }

    /**
     * Open period for specific modules
     */
    public function openPeriod(string $periodId, array $modules = ['GL', 'BNK', 'RM', 'PM']): bool
    {
        $period = FiscalPeriod::find($periodId);
        
        if (!$period) {
            throw new \Exception("Period {$periodId} not found");
        }

        foreach ($modules as $module) {
            $period->openFor($module);
        }

        return true;
    }

    /**
     * Get period status for all modules
     */
    public function getPeriodStatus(string $periodId): array
    {
        $period = FiscalPeriod::find($periodId);
        
        if (!$period) {
            throw new \Exception("Period {$periodId} not found");
        }

        return [
            'period_id' => $period->period_id,
            'fiscal_year' => $period->fiscal_year,
            'period_number' => $period->period_number,
            'start_date' => $period->start_date,
            'end_date' => $period->end_date,
            'status' => [
                'GL' => $period->is_open_gl ? 'OPEN' : 'CLOSED',
                'BNK' => $period->is_open_bnk ? 'OPEN' : 'CLOSED',
                'RM' => $period->is_open_rm ? 'OPEN' : 'CLOSED',
                'PM' => $period->is_open_pm ? 'OPEN' : 'CLOSED'
            ]
        ];
    }

    /**
     * Validate period closure (ensure no pending transactions)
     */
    public function validatePeriodClosure(string $periodId, string $module): array
    {
        $period = FiscalPeriod::find($periodId);
        $errors = [];

        if (!$period) {
            $errors[] = "Period {$periodId} not found";
            return $errors;
        }

        // Add validation logic here for pending transactions
        // This would check for unbalanced transactions, pending approvals, etc.

        return $errors;
    }

    /**
     * Get current open periods
     */
    public function getOpenPeriods(string $module = 'GL'): array
    {
        $field = match(strtoupper($module)) {
            'GL' => 'is_open_gl',
            'BNK' => 'is_open_bnk',
            'RM' => 'is_open_rm',
            'PM' => 'is_open_pm',
            default => 'is_open_gl'
        };

        return FiscalPeriod::where($field, true)
                          ->orderBy('fiscal_year')
                          ->orderBy('period_number')
                          ->get()
                          ->toArray();
    }
}
