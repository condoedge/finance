<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Facades\IntegrityChecker;
use Illuminate\Console\Command;

class EnsureIntegrityCommand extends Command
{
    public $signature = 'finance:ensure-integrity
        {--model= : Specific model class to check integrity}
        {--since-days= : Only rows created in the last N days, propagated to their parents}';

    public $description = 'Check and fix financial models integrity';

    public function handle()
    {
        $this->info('Checking financial data integrity...');

        $modelClass = $this->option('model');
        $sinceDays = $this->option('since-days');

        if ($modelClass) {
            $this->info("Checking integrity of {$modelClass} and its descendants");
            IntegrityChecker::checkChildrenThenModel($modelClass);
        } elseif ($sinceDays !== null) {
            $this->info("Checking integrity of rows created in the last {$sinceDays} day(s)");
            IntegrityChecker::checkRecentIntegrity(now()->subDays((int) $sinceDays));
        } else {
            $this->info('Checking full integrity of all models');
            IntegrityChecker::checkFullIntegrity();
        }

        $this->info('Integrity check completed successfully!');
    }
}
