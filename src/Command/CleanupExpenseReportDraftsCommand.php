<?php

namespace Condoedge\Finance\Command;

use \Illuminate\Console\Command;

class CleanupExpenseReportDraftsCommand extends Command
{
    protected $signature = 'cleanup:expense-report-drafts';
    protected $description = 'Cleanup draft expense reports';

    public function handle()
    {
        $deletedCount = \Condoedge\Finance\Models\ExpenseReport::where('is_draft', true)
            ->delete();

        $this->info("Deleted {$deletedCount} draft expense reports.");
    }
}