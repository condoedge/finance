<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Services\FiscalYearService;
use Illuminate\Console\Command;

/**
 * Close Fiscal Period Command
 * 
 * Closes fiscal periods for specific modules
 */
class CloseFiscalPeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:close-period 
                            {period_id : The period ID (e.g., per01-2025)}
                            {--modules=* : Modules to close (GL,BNK,RM,PM)}
                            {--all : Close all modules}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Close fiscal period for specific modules';

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
            
            // Determine which modules to close
            if ($all) {
                $modulesToClose = ['GL', 'BNK', 'RM', 'PM'];
            } elseif (!empty($modules)) {
                $modulesToClose = array_map('strtoupper', $modules);
            } else {
                $modulesToClose = $this->askForModules();
            }
            
            // Validate modules
            $validModules = ['GL', 'BNK', 'RM', 'PM'];
            $invalidModules = array_diff($modulesToClose, $validModules);
            if (!empty($invalidModules)) {
                $this->error('Invalid modules: ' . implode(', ', $invalidModules));
                $this->info('Valid modules: ' . implode(', ', $validModules));
                return Command::FAILURE;
            }
            
            // Show current period status
            $this->displayPeriodStatus($period);
            
            // Filter to only modules that are currently open
            $actuallyClosing = [];
            foreach ($modulesToClose as $module) {
                if ($period->isOpenForModule($module)) {
                    $actuallyClosing[] = $module;
                }
            }
            
            if (empty($actuallyClosing)) {
                $this->warn('All specified modules are already closed for this period.');
                return Command::SUCCESS;
            }
            
            $this->info("\nModules to close: " . implode(', ', $actuallyClosing));
            
            if (!$force && !$this->confirm('Are you sure you want to close these modules?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
            
            // Close the period
            $updatedPeriod = $fiscalService->closePeriod($periodId, $actuallyClosing);
            
            $this->info("✓ Period {$periodId} closed successfully for modules: " . implode(', ', $actuallyClosing));
            
            // Show updated status
            $this->info("\nUpdated period status:");
            $this->displayPeriodStatus($updatedPeriod);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to close period: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
    
    /**
     * Ask user which modules to close
     */
    protected function askForModules(): array
    {
        $this->info('Select modules to close:');
        
        $choices = [];
        if ($this->confirm('Close GL (General Ledger)?')) {
            $choices[] = 'GL';
        }
        if ($this->confirm('Close BNK (Bank)?')) {
            $choices[] = 'BNK';
        }
        if ($this->confirm('Close RM (Receivables)?')) {
            $choices[] = 'RM';
        }
        if ($this->confirm('Close PM (Payables)?')) {
            $choices[] = 'PM';
        }
        
        if (empty($choices)) {
            $this->warn('No modules selected for closing.');
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
