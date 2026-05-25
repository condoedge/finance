<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Billing\Settlement\Contracts\SettlementImportServiceInterface;
use Illuminate\Console\Command;

class ImportSettlementReportCommand extends Command
{
    protected $signature = 'finance:import-settlement {provider} {file}';

    protected $description = 'Import a payment provider settlement report and record processor fees.';

    public function handle(SettlementImportServiceInterface $service): int
    {
        $provider = $this->argument('provider');
        $file = $this->argument('file');

        if (!is_readable($file)) {
            $this->error("File not readable: {$file}");
            return self::FAILURE;
        }

        $result = $service->import($provider, $file);

        $this->info("Settlement import — {$result->providerCode}");
        $this->line("  Rows parsed : {$result->rowsParsed}");
        $this->line("  Matched     : {$result->matched}");
        $this->line("  Unmatched   : {$result->unmatched}");

        if ($result->unmatched > 0) {
            $this->warn('  Unmatched refs (first 50): ' . implode(', ', $result->unmatchedRefs));
        }

        return self::SUCCESS;
    }
}
