<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Services\FiscalYearService;
use Illuminate\Console\Command;

/**
 * Generate Fiscal Periods Command
 * 
 * Creates fiscal periods for a fiscal year
 */
class GenerateFiscalPeriodsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:generate-periods 
                            {team_id : The team ID}
                            {fiscal_year : The fiscal year (e.g., 2025)}
                            {--regenerate : Delete existing periods and regenerate}
                            {--custom : Generate custom periods instead of monthly}';

    /**
     * The console command description.
     */
    protected $description = 'Generate fiscal periods for a fiscal year';

    /**
     * Execute the console command.
     */
    public function handle(FiscalYearService $fiscalService): int
    {
        $teamId = (int) $this->argument('team_id');
        $fiscalYear = (int) $this->argument('fiscal_year');
        $regenerate = $this->option('regenerate');
        $custom = $this->option('custom');
        
        try {
            // Verify fiscal year setup exists
            $fiscalSetup = \Condoedge\Finance\Models\FiscalYearSetup::getActiveForTeam($teamId);
            if (!$fiscalSetup) {
                $this->error("No fiscal year setup found for team {$teamId}. Run 'finance:setup-fiscal-year' first.");
                return Command::FAILURE;
            }
            
            // Check if periods already exist
            $existingPeriods = \Condoedge\Finance\Models\FiscalPeriod::where('team_id', $teamId)
                ->where('fiscal_year', $fiscalYear)
                ->count();
                
            if ($existingPeriods > 0 && !$regenerate) {
                $this->error("Periods already exist for fiscal year {$fiscalYear}. Use --regenerate to recreate them.");
                return Command::FAILURE;
            }
            
            $this->info("Generating periods for fiscal year {$fiscalYear}...");
            $this->info("Team ID: {$teamId}");
            $this->info("Fiscal start date: {$fiscalSetup->company_fiscal_start_date->format('Y-m-d')}");
            
            if ($regenerate) {
                $this->warn('This will DELETE existing periods and recreate them!');
            }
            
            if (!$this->confirm('Do you want to proceed?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
            
            if ($custom) {
                return $this->generateCustomPeriods($fiscalService, $teamId, $fiscalYear);
            }
            
            $periods = $fiscalService->generateFiscalPeriods($teamId, $fiscalYear, $regenerate);
            
            $this->info("✓ Generated {count($periods)} fiscal periods successfully!");
            
            // Display created periods
            $this->table(
                ['Period ID', 'Period Number', 'Start Date', 'End Date', 'Status'],
                collect($periods)->map(function ($period) {
                    return [
                        $period->period_id,
                        $period->period_number,
                        $period->start_date->format('Y-m-d'),
                        $period->end_date->format('Y-m-d'),
                        'Open (All Modules)'
                    ];
                })->toArray()
            );
            
            $this->info("All periods are initially OPEN for all modules (GL, BNK, RM, PM)");
            $this->info("Use 'finance:close-period' command to close specific periods");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to generate periods: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    /**
     * Generate custom periods interactively
     */
    protected function generateCustomPeriods(FiscalYearService $fiscalService, int $teamId, int $fiscalYear): int
    {
        $this->info('Custom period generation mode');
        $this->info('You will be prompted to create periods one by one.');
        
        $periods = [];
        $periodNumber = 1;
        
        while (true) {
            $this->info("\n--- Creating Period #{$periodNumber} ---");
            
            $periodCode = $this->ask('Period code (e.g., per01, qtr1)', "per{str_pad($periodNumber, 2, '0', STR_PAD_LEFT)}");
            $startDate = $this->ask('Start date (YYYY-MM-DD)');
            $endDate = $this->ask('End date (YYYY-MM-DD)');
            
            try {
                $startCarbon = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate);
                $endCarbon = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate);
                
                $period = $fiscalService->createCustomPeriod(
                    $teamId,
                    $fiscalYear,
                    $periodCode,
                    $startCarbon,
                    $endCarbon
                );
                
                $periods[] = $period;
                $this->info("✓ Created period {$period->period_id}");
                
                $periodNumber++;
                
                if (!$this->confirm('Create another period?')) {
                    break;
                }
                
            } catch (\Exception $e) {
                $this->error("Failed to create period: {$e->getMessage()}");
                if (!$this->confirm('Try again?')) {
                    break;
                }
            }
        }
        
        if (empty($periods)) {
            $this->warn('No periods were created.');
            return Command::SUCCESS;
        }
        
        $this->info("\n✓ Created {count($periods)} custom periods successfully!");
        
        // Display created periods
        $this->table(
            ['Period ID', 'Start Date', 'End Date', 'Status'],
            collect($periods)->map(function ($period) {
                return [
                    $period->period_id,
                    $period->start_date->format('Y-m-d'),
                    $period->end_date->format('Y-m-d'),
                    'Open (All Modules)'
                ];
            })->toArray()
        );
        
        return Command::SUCCESS;
    }
}
