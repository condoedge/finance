<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Facades\IntegrityChecker;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Models\Invoice;
use Illuminate\Console\Command;

class EnsureIntegrityCommand extends Command
{
    public $signature = 'finance:ensure-integrity {--model= : Specific model class to check integrity}';
    
    public $description = 'Check and fix financial models integrity';

    public function handle()
    {
        $this->info('Checking financial data integrity...');

        $modelClass = $this->option('model');
        
        if ($modelClass) {
            $this->info("Checking integrity of {$modelClass} and its descendants");
            IntegrityChecker::checkChildrenThenModel($modelClass);
        } else {
            $this->info('Checking full integrity of all models');
            IntegrityChecker::checkFullIntegrity();
        }
        
        $this->info('Integrity check completed successfully!');
    }
}