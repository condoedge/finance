<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Services\AccountSegmentService;
use Illuminate\Console\Command;

/**
 * Setup Account Segment System Command
 * 
 * Initializes the segment-based account system with:
 * - Default segment structure (Parent Team, Team, Natural Account)
 * - Sample segment values for demonstration
 */
class SetupAccountSegmentSystemCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'finance:setup-segments {--sample : Include sample segment values}';

    /**
     * The console command description.
     */
    protected $description = 'Setup the account segment system with default structure and optional sample values';

    /**
     * Execute the console command.
     */
    public function handle(AccountSegmentService $segmentService): int
    {
        $this->info('Setting up account segment system...');
        
        try {
            // Setup default segment structure
            $this->info('Creating default segment structure...');
            $segmentService->setupDefaultSegmentStructure();
            $this->info('✓ Segment structure created: Parent Team (2), Team (2), Natural Account (4)');
            
            // Setup sample values if requested
            if ($this->option('sample')) {
                $this->info('Creating sample segment values...');
                $segmentService->setupSampleSegmentValues();
                $this->info('✓ Sample segment values created');
                
                // Show format mask
                $formatMask = $segmentService->getAccountFormatMask();
                $this->info("Account format: {$formatMask}");
                
                // Show examples
                $this->info('Example accounts can now be created:');
                $this->line('  - 10-03-4000 (parent_team_10 - team_03 - Cash Account)');
                $this->line('  - 10-03-1105 (parent_team_10 - team_03 - Material Expense)');
                $this->line('  - 20-04-4105 (parent_team_20 - team_04 - Fuel Expense)');
            }
            
            // Validate structure
            $issues = $segmentService->validateSegmentStructure();
            if (empty($issues)) {
                $this->info('✓ Segment structure validation passed');
            } else {
                $this->warn('Segment structure validation issues:');
                foreach ($issues as $issue) {
                    $this->line("  - {$issue}");
                }
            }
            
            $this->info('Account segment system setup completed successfully!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Failed to setup account segment system: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
