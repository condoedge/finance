<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Billing\Settlement\Fetch\SettlementFetchService;
use Illuminate\Console\Command;

class FetchSettlementReportsCommand extends Command
{
    protected $signature = 'finance:fetch-settlements
                            {--provider=moneris : Provider code to fetch for}
                            {--since= : Only consider remote files newer than YYYY-MM-DD}
                            {--dry-run : Connect and list but do not download or import}';

    protected $description = 'Fetch and import provider settlement reports.';

    public function handle(SettlementFetchService $service): int
    {
        $provider = (string) $this->option('provider');
        $since = $this->option('since') ? new \DateTimeImmutable((string) $this->option('since')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $o = $service->run($provider, since: $since, dryRun: $dryRun);

        if ($o->error === 'no-credentials') {
            $this->warn("No SFTP source configured for provider '{$provider}'.");
            return self::SUCCESS;
        }

        $msg = sprintf(
            '[%s] provider=%s downloaded=%d imported=%d skipped=%d%s',
            $o->isSuccess() ? 'OK' : 'FAIL', $o->providerCode,
            $o->filesDownloaded, $o->filesImported, $o->filesSkipped,
            $o->error ? " error={$o->error}" : '',
        );
        $o->isSuccess() ? $this->info($msg) : $this->error($msg);

        return $o->isSuccess() ? self::SUCCESS : self::FAILURE;
    }
}
