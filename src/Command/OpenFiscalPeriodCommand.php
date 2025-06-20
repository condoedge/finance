<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Services\FiscalYearService;
use Illuminate\Console\Command;

/**
 * Open Fiscal Period Command
 * 
 * Opens fiscal periods for specific modules
 */
class OpenFiscalPeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:open-period 
                            {period_id : The period ID (e.g., per01-2025)}
                            {--modules=* : Modules to open (GL,BNK,RM,PM)}
                            {--all : Open all modules}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Open fiscal period for specific modules';

    /**
     * Execute the console command.
     */
    public function handle(FiscalYearService $fiscalService): int
    {
        $periodId = $this->argument('period_id');
        $modules = $this->option('modules');
        $all = $this->option('all');
        $force = $this->option('force');
        
        try {
            // Find the period
            $period = \Condoedge\Finance\Models\FiscalPeriod::find($periodId);
            if (!$period) {
                $this->error("Period {$periodId} not found.");
                return Command::FAILURE;
            }
            
            // Determine which modules to open
            if ($all) {
                $modulesToOpen = ['GL', 'BNK', 'RM', 'PM'];
            } elseif (!empty($modules)) {
                $modulesToOpen = array_map('strtoupper', $modules);
            } else {
                $modulesToOpen = $this->askForModules();
            }
            
            // Validate modules
            $validModules = ['GL', 'BNK', 'RM', 'PM'];
            $invalidModules = array_diff($modulesToOpen, $validModules);
            if (!empty($invalidModules)) {
                $this->error('Invalid modules: ' . implode(', ', $invalidModules));
                $this->info('Valid modules: ' . implode(', ', $validModules));
                return Command::FAILURE;
            }
            
            // Show current period status
            $this->displayPeriodStatus($period);
            
            // Filter to only modules that are currently closed
            $actuallyOpening = [];
            foreach ($modulesToOpen as $module) {
                if (!$period->isOpenForModule($module)) {
                    $actuallyOpening[] = $module;
                }
            }
            
            if (empty($actuallyOpening)) {
                $this->warn('All specified modules are already open for this period.');
                return Command::SUCCESS;
            }
            
            $this->info("\nModules to open: " . implode(', ', $actuallyOpening));
            $this->warn('WARNING: Opening periods allows transaction posting. Ensure this is intentional.');
            
            if (!$force && !$this->confirm('Are you sure you want to open these modules?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
            
            // Open the period
            $updatedPeriod = $fiscalService->openPeriod($periodId, $actuallyOpening);
            
            $this->info("✓ Period {$periodId} opened successfully for modules: " . implode(', ', $actuallyOpening));
            
            // Show updated status
            $this->info("\nUpdated period status:");
            $this->displayPeriodStatus($updatedPeriod);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to open period: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    /**
     * Ask user which modules to open
     */
    protected function askForModules(): array
    {
        $this->info('Select modules to open:');
        
        $choices = [];
        if ($this->confirm('Open GL (General Ledger)?')) {
            $choices[] = 'GL';
        }
        if ($this->confirm('Open BNK (Bank)?')) {
            $choices[] = 'BNK';
        }
        if ($this->confirm('Open RM (Receivables)?')) {
            $choices[] = 'RM';
        }
        if ($this->confirm('Open PM (Payables)?')) {
            $choices[] = 'PM';
        }
        
        if (empty($choices)) {
            $this->warn('No modules selected for opening.');
        }
        
        return $choices;
    }
    
    /**
     * Display period status in a table
     */
    protected function displayPeriodStatus(\Condoedge\Finance\Models\FiscalPeriod $period): void
    {
        $this->info("Period: {$period->period_id} ({$period->start_date->format('Y-m-d')} to {$period->end_date->format('Y-m-d')})");
        
        $this->table(
            ['Module', 'Status'],
            [
                ['GL (General Ledger)', $period->is_open_gl ? 'OPEN' : 'CLOSED'],
                ['BNK (Bank)', $period->is_open_bnk ? 'OPEN' : 'CLOSED'],
                ['RM (Receivables)', $period->is_open_rm ? 'OPEN' : 'CLOSED'],
                ['PM (Payables)', $period->is_open_pm ? 'OPEN' : 'CLOSED'],
            ]
        );
    }
}
