<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Services\FiscalYearService;
use Illuminate\Console\Command;

/**
 * Pre-Create Fiscal Periods Command
 * 
 * Creates fiscal periods for the upcoming month for all teams.
 * Should be scheduled to run before month starts (e.g., 30 minutes before midnight on last day).
 * 
 * Example cron schedule:
 * 30 23 L * * php artisan finance:pre-create-periods
 * (Runs at 23:30 on the last day of every month)
 */
class PreCreateFiscalPeriodsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:pre-create-periods 
                            {--days-ahead=1 : Number of days to look ahead (default: 1)}
                            {--dry-run : Run without actually creating periods}';

    /**
     * The console command description.
     */
    protected $description = 'Pre-create fiscal periods for the upcoming month for all teams';

    /**
     * Execute the console command.
     */
    public function handle(FiscalYearService $fiscalService): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $dryRun = $this->option('dry-run');
        
        $upcomingDate = now()->addDays($daysAhead)->startOfMonth();
        
        $this->info('Pre-Creating Fiscal Periods');
        $this->info('===========================');
        $this->info('Target month: ' . $upcomingDate->format('F Y'));
        $this->info('Running at: ' . now()->format('Y-m-d H:i:s'));
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No periods will be created');
        }
        
        $this->newLine();
        
        try {
            if ($dryRun) {
                // In dry run, just show what would be created
                $teams = \Condoedge\Finance\Models\FiscalYearSetup::query()
                    ->with('team')
                    ->get();
                
                $this->info('Would create periods for ' . $teams->count() . ' teams:');
                
                foreach ($teams as $setup) {
                    $fiscalYear = $fiscalService->getFiscalYearForDate($upcomingDate, $setup->team_id);
                    $periodNumber = $fiscalService->calculatePeriodNumber($upcomingDate, $setup->team_id);
                    
                    // Check if already exists
                    $exists = \Condoedge\Finance\Models\FiscalPeriod::where('team_id', $setup->team_id)
                        ->where('fiscal_year', $fiscalYear)
                        ->where('period_number', $periodNumber)
                        ->exists();
                    
                    $this->line(sprintf(
                        '  Team %d: Period %s (%s - %s) %s',
                        $setup->team_id,
                        $periodNumber,
                        $upcomingDate->format('Y-m-d'),
                        $upcomingDate->copy()->endOfMonth()->format('Y-m-d'),
                        $exists ? '[ALREADY EXISTS]' : '[WOULD CREATE]'
                    ));
                }
                
                return Command::SUCCESS;
            }

            $fiscalService->preCreateUpcomingPeriods($daysAhead, true);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to pre-create periods: ' . $e->getMessage());
        
            return Command::FAILURE;
        }
    }
}
