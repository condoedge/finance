<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Services\FiscalYearService;
use Illuminate\Console\Command;

/**
 * View Fiscal Period Status Command
 * 
 * Displays fiscal period status in the format requested by the user
 */
class ViewFiscalPeriodStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:period-status 
                            {team_id : The team ID}
                            {--fiscal_year= : Specific fiscal year to view}
                            {--current : Show current fiscal year only}
                            {--period= : Show specific period only}';

    /**
     * The console command description.
     */
    protected $description = 'View fiscal period status for modules (GL, BNK, RM, PM)';

    /**
     * Execute the console command.
     */
    public function handle(FiscalYearService $fiscalService): int
    {
        $teamId = (int) $this->argument('team_id');
        $fiscalYear = $this->option('fiscal_year');
        $current = $this->option('current');
        $specificPeriod = $this->option('period');
        
        try {
            // Verify fiscal year setup exists
            $fiscalSetup = \Condoedge\Finance\Models\FiscalYearSetup::getActiveForTeam($teamId);
            if (!$fiscalSetup) {
                $this->error("No fiscal year setup found for team {$teamId}.");
                return Command::FAILURE;
            }
            
            if ($specificPeriod) {
                return $this->showSpecificPeriod($specificPeriod);
            }
            
            if ($current) {
                $fiscalYear = $fiscalService->getCurrentFiscalYear($teamId);
                if (!$fiscalYear) {
                    $this->error('Unable to determine current fiscal year.');
                    return Command::FAILURE;
                }
            }
            
            if (!$fiscalYear) {
                // Show available fiscal years
                $availableYears = \Condoedge\Finance\Models\FiscalPeriod::where('team_id', $teamId)
                    ->distinct()
                    ->pluck('fiscal_year')
                    ->sort();
                    
                if ($availableYears->isEmpty()) {
                    $this->error("No fiscal periods found for team {$teamId}. Run 'finance:generate-periods' first.");
                    return Command::FAILURE;
                }
                
                $this->info('Available fiscal years: ' . $availableYears->implode(', '));
                $fiscalYear = $this->ask('Which fiscal year do you want to view?', $availableYears->last());
            }
            
            $this->showFiscalYearStatus($fiscalService, $teamId, (int) $fiscalYear);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to retrieve period status: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    /**
     * Show status for a specific period
     */
    protected function showSpecificPeriod(string $periodId): int
    {
        $period = \Condoedge\Finance\Models\FiscalPeriod::find($periodId);
        if (!$period) {
            $this->error("Period {$periodId} not found.");
            return Command::FAILURE;
        }
        
        $this->info("=== Period Status: {$periodId} ===");
        $this->info("Period: {$period->start_date->format('Y-m-d')} to {$period->end_date->format('Y-m-d')}");
        $this->info("Fiscal Year: {$period->fiscal_year}");
        
        $this->table(
            ['Module', 'Status', 'Numeric'],
            [
                ['GL (General Ledger)', $period->is_open_gl ? 'OPEN' : 'CLOSED', $period->is_open_gl ? '1' : '0'],
                ['BNK (Bank)', $period->is_open_bnk ? 'OPEN' : 'CLOSED', $period->is_open_bnk ? '1' : '0'],
                ['RM (Receivables)', $period->is_open_rm ? 'OPEN' : 'CLOSED', $period->is_open_rm ? '1' : '0'],
                ['PM (Payables)', $period->is_open_pm ? 'OPEN' : 'CLOSED', $period->is_open_pm ? '1' : '0'],
            ]
        );
        
        return Command::SUCCESS;
    }
    
    /**
     * Show status for entire fiscal year in the requested format
     */
    protected function showFiscalYearStatus(FiscalYearService $fiscalService, int $teamId, int $fiscalYear): void
    {
        $summary = $fiscalService->getFiscalYearSummary($teamId, $fiscalYear);
        
        $this->info("=== Fiscal Year {$fiscalYear} Status ===");
        $this->info("Team ID: {$teamId}");
        $this->info("Fiscal Period: {$summary['fiscal_start_date']->format('Y-m-d')} to {$summary['fiscal_end_date']->format('Y-m-d')}");
        $this->info("Total Periods: {$summary['total_periods']}");
        
        // Show closure status summary
        $this->info("\n=== Fiscal Year Closure Status ===");
        $this->table(
            ['Module', 'Status'],
            [
                ['GL', $summary['closure_status']['GL'] ? 'FULLY CLOSED' : 'OPEN PERIODS EXIST'],
                ['BNK', $summary['closure_status']['BNK'] ? 'FULLY CLOSED' : 'OPEN PERIODS EXIST'],
                ['RM', $summary['closure_status']['RM'] ? 'FULLY CLOSED' : 'OPEN PERIODS EXIST'],
                ['PM', $summary['closure_status']['PM'] ? 'FULLY CLOSED' : 'OPEN PERIODS EXIST'],
            ]
        );
        
        // Show individual periods in the exact format requested
        $this->info("\n=== Period Details ===");
        $this->info("PERIOD                           IS OPEN (1) OR IS CLOSED (0)");
        $this->info("                                 GL     BNK     RM     PM");
        $this->info(str_repeat("-", 70));
        
        foreach ($summary['periods'] as $periodData) {
            $period = $periodData['period'];
            $display = $periodData['period_display'];
            
            // Format: per01 from 2024-05-01 to 2024-05-31    1      1       1      1
            $line = sprintf(
                "%-32s %s      %s       %s      %s",
                $display,
                $period->is_open_gl ? '1' : '0',
                $period->is_open_bnk ? '1' : '0',
                $period->is_open_rm ? '1' : '0',
                $period->is_open_pm ? '1' : '0'
            );
            
            // Color code the line
            if ($period->is_open_gl || $period->is_open_bnk || $period->is_open_rm || $period->is_open_pm) {
                $this->line($line); // Normal color for open periods
            } else {
                $this->comment($line); // Gray for fully closed periods
            }
        }
        
        // Show legend
        $this->info("\nLegend: 1 = OPEN, 0 = CLOSED");
        $this->info("GL = General Ledger, BNK = Bank, RM = Receivables, PM = Payables");
        
        $period = $summary['periods']->first()['period']->period_id ?? 'per01-' . $fiscalYear;

        // Show helpful commands
        $this->info("\n=== Available Commands ===");
        $this->info("Close period: finance:close-period {period-id} --modules=GL,BNK");
        $this->info("Open period:  finance:open-period {period-id} --modules=GL,BNK");
        $this->info("View specific: finance:period-status {$teamId} --period={$period}");
    }
}
