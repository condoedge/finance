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
                    $periodNumber = $this->calculatePeriodNumber($upcomingDate, $setup);
                    
                    // Check if already exists
                    $exists = \Condoedge\Finance\Models\FiscalPeriod::where('team_id', $setup->team_id)
                        ->where('fiscal_year', $fiscalYear)
                        ->where('period_number', $periodNumber)
                        ->exists();
                    
                    $this->line(sprintf(
                        '  Team %d: Period %s (%s - %s) %s',
                        $setup->team_id,
                        $periodId,
                        $upcomingDate->format('Y-m-d'),
                        $upcomingDate->copy()->endOfMonth()->format('Y-m-d'),
                        $exists ? '[ALREADY EXISTS]' : '[WOULD CREATE]'
                    ));
                }
                
                return Command::SUCCESS;
            }
            
            // Actually create periods
            $results = $fiscalService->preCreateUpcomingPeriods($daysAhead, true);
            
            $created = collect($results)->where('status', 'created')->count();
            $failed = collect($results)->where('status', 'failed')->count();
            $total = count($results);
            
            $this->info("Results: {$created} created, {$failed} failed out of {$total} teams");
            
            // Log summary
            \Log::info('Pre-created fiscal periods', [
                'command' => 'finance:pre-create-periods',
                'target_month' => $upcomingDate->format('Y-m'),
                'created' => $created,
                'failed' => $failed,
                'total' => $total
            ]);
            
            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to pre-create periods: ' . $e->getMessage());
            
            \Log::error('Pre-create periods command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Calculate period number for display in dry run
     */
    private function calculatePeriodNumber(\Carbon\Carbon $date, $fiscalSetup): int
    {
        $fiscalStartMonth = $fiscalSetup->fiscal_start_date->month;
        $fiscalStartDay = $fiscalSetup->fiscal_start_date->day;
        
        $currentYearFiscalStart = \Carbon\Carbon::create($date->year, $fiscalStartMonth, $fiscalStartDay);
        if ($date->lt($currentYearFiscalStart)) {
            $currentYearFiscalStart->subYear();
        }
        
        $monthsFromStart = $currentYearFiscalStart->diffInMonths($date) + 1;
        
        return min(max($monthsFromStart, 1), 12);
    }
}
