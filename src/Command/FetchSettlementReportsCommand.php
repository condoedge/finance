<?php

namespace Condoedge\Finance\Command;

use Condoedge\Finance\Billing\Settlement\Fetch\SettlementFetchService;
use Illuminate\Console\Command;

class FetchSettlementReportsCommand extends Command
{
    protected $signature = 'finance:fetch-settlements
                            {--provider=moneris : Provider code to fetch for}
                            {--team= : Limit to a single team_id}
                            {--since= : Only consider remote files newer than YYYY-MM-DD}
                            {--dry-run : Connect and list but do not download or import}';

    protected $description = 'Fetch and import provider settlement reports for one or all teams.';

    public function handle(SettlementFetchService $service): int
    {
        $provider = (string) $this->option('provider');
        $team = $this->option('team') !== null ? (int) $this->option('team') : null;
        $since = $this->option('since') ? new \DateTimeImmutable((string) $this->option('since')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $outcomes = $service->run($provider, teamId: $team, since: $since, dryRun: $dryRun);

        $successCount = 0;
        foreach ($outcomes as $o) {
            $tag = $o->isSuccess() ? 'OK' : 'FAIL';
            $msg = sprintf(
                '[%s] team=%d provider=%s downloaded=%d imported=%d skipped=%d%s',
                $tag, $o->teamId, $o->providerCode,
                $o->filesDownloaded, $o->filesImported, $o->filesSkipped,
                $o->error ? " error={$o->error}" : '',
            );
            $o->isSuccess() ? $this->info($msg) : $this->error($msg);
            if ($o->isSuccess()) {
                $successCount++;
            }
        }

        if (empty($outcomes)) {
            $this->warn("No teams with provider '{$provider}' active.");
            return self::SUCCESS;
        }

        return $successCount > 0 ? self::SUCCESS : self::FAILURE;
    }
}
