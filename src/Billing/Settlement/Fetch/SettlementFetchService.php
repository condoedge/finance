<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

use Condoedge\Finance\Billing\Settlement\Contracts\SettlementImportServiceInterface;
use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\SettlementFetcherInterface;
use Condoedge\Finance\Models\ProviderCredentials;
use Condoedge\Finance\Models\SettlementFile;
use Condoedge\Finance\Models\TeamPaymentProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Per-team orchestrator: resolves source config, drives the fetcher, persists
 * the ledger, calls the import service. Per-team try/catch guarantees one
 * tenant's broken creds never blocks the others.
 */
class SettlementFetchService
{
    /**
     * @param array<string, SettlementFetcherInterface> $fetchers keyed by provider code
     */
    public function __construct(
        protected array $fetchers,
        protected SettlementImportServiceInterface $importer,
    ) {
    }

    /**
     * Run a fetch+import cycle. When $teamId is null, iterates all active
     * teams for the provider; when provided, runs for just that team.
     *
     * @return array<int, TeamFetchOutcome>
     */
    public function run(string $providerCode, ?int $teamId = null, ?\DateTimeImmutable $since = null, bool $dryRun = false): array
    {
        $fetcher = $this->fetchers[$providerCode] ?? null;
        if ($fetcher === null) {
            throw new \InvalidArgumentException("No fetcher registered for provider '{$providerCode}'.");
        }

        $teamIds = $teamId !== null ? [$teamId] : $this->teamsWithProvider($providerCode);

        $outcomes = [];
        foreach ($teamIds as $tid) {
            $outcomes[] = $this->runForTeam($fetcher, $providerCode, (int) $tid, $since, $dryRun);
        }
        return $outcomes;
    }

    protected function runForTeam(
        SettlementFetcherInterface $fetcher,
        string $providerCode,
        int $teamId,
        ?\DateTimeImmutable $since,
        bool $dryRun,
    ): TeamFetchOutcome {
        try {
            $source = $this->resolveSource($teamId, $providerCode);
            if ($source === null) {
                Log::channel($this->logChannel())->warning(
                    "[settlement-fetch] team {$teamId} / {$providerCode}: no SFTP credentials, skipping",
                );
                return new TeamFetchOutcome($teamId, $providerCode, error: 'no-credentials');
            }

            $downloaded = 0;
            $imported = 0;
            $skipped = 0;

            $localDisk = (string) config('finance-settlement.fetch.local_disk', 'settlement-reports');

            foreach ($fetcher->listAvailable($source, $since) as $file) {
                $existing = SettlementFile::findFor($teamId, $providerCode, $file->remoteFilename);

                if ($existing && $existing->imported_at !== null) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $skipped++;
                    continue;
                }

                if (!$existing) {
                    $localPath = sprintf('%s/%d/%s', $providerCode, $teamId, $file->remoteFilename);
                    $sha = $fetcher->downloadTo($source, $file, $localDisk, $localPath);

                    if (SettlementFile::findBySha($teamId, $providerCode, $sha)) {
                        // Content-level duplicate: another filename had this exact body.
                        Storage::disk($localDisk)->delete($localPath);
                        $skipped++;
                        continue;
                    }

                    $existing = SettlementFile::markFetched(
                        teamId: $teamId,
                        providerCode: $providerCode,
                        filename: $file->remoteFilename,
                        localPath: $localPath,
                        size: $file->remoteSize,
                        sha256: $sha,
                    );
                    $downloaded++;
                }

                $this->importFile($existing, $providerCode, $localDisk);
                if ($existing->fresh()->imported_at !== null) {
                    $imported++;
                }
            }

            return new TeamFetchOutcome(
                teamId: $teamId,
                providerCode: $providerCode,
                filesDownloaded: $downloaded,
                filesImported: $imported,
                filesSkipped: $skipped,
            );
        } catch (\Throwable $e) {
            Log::channel($this->logChannel())->error(
                "[settlement-fetch] team {$teamId} / {$providerCode} failed: " . $e->getMessage(),
                ['exception' => $e],
            );
            return new TeamFetchOutcome(
                teamId: $teamId,
                providerCode: $providerCode,
                error: $e->getMessage(),
            );
        }
    }

    protected function importFile(SettlementFile $row, string $providerCode, string $localDisk): void
    {
        try {
            $absolutePath = Storage::disk($localDisk)->path($row->local_path);
            $result = $this->importer->import($providerCode, $absolutePath);
            $row->markImported($result);
        } catch (\Throwable $e) {
            $row->markFailed($e->getMessage());
            Log::channel($this->logChannel())->error(
                "[settlement-fetch] import failed for team {$row->team_id} / file {$row->remote_filename}: " . $e->getMessage(),
            );
        }
    }

    protected function resolveSource(int $teamId, string $providerCode): ?SettlementSourceConfig
    {
        $creds = ProviderCredentials::lookup($teamId, $providerCode, isTest: false);
        $teamCreds = $creds?->credentials ?? [];
        $envDefaults = (array) config("finance-settlement.providers.{$providerCode}.sftp", []);
        $envAuth = $this->envAuth($envDefaults);

        $auth = array_filter([
            'sftp_host' => $teamCreds['sftp_host'] ?? $envAuth['sftp_host'] ?? null,
            'sftp_username' => $teamCreds['sftp_username'] ?? $envAuth['sftp_username'] ?? null,
            'sftp_private_key' => $teamCreds['sftp_private_key'] ?? null,
            'sftp_private_key_path' => $envAuth['sftp_private_key_path'] ?? null,
            'sftp_private_key_passphrase' => $teamCreds['sftp_private_key_passphrase'] ?? $envAuth['sftp_private_key_passphrase'] ?? null,
            'sftp_host_fingerprint' => $teamCreds['sftp_host_fingerprint'] ?? $envAuth['sftp_host_fingerprint'] ?? null,
            'sftp_port' => $teamCreds['sftp_port'] ?? $envAuth['sftp_port'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $required = ['sftp_host', 'sftp_username', 'sftp_host_fingerprint'];
        foreach ($required as $key) {
            if (!isset($auth[$key])) {
                return null;
            }
        }
        if (!isset($auth['sftp_private_key']) && !isset($auth['sftp_private_key_path'])) {
            return null;
        }

        $remotePath = $teamCreds['sftp_remote_path']
            ?? $envDefaults['remote_path']
            ?? '/';

        $lookback = (int) config('finance-settlement.fetch.lookback_days', 30);

        return new SettlementSourceConfig(
            teamId: $teamId,
            providerCode: $providerCode,
            remotePath: $remotePath,
            auth: $auth,
            lookbackDays: $lookback,
        );
    }

    protected function envAuth(array $envDefaults): array
    {
        return [
            'sftp_host' => $envDefaults['host'] ?? null,
            'sftp_username' => $envDefaults['username'] ?? null,
            'sftp_private_key_path' => $envDefaults['private_key_path'] ?? null,
            'sftp_private_key_passphrase' => $envDefaults['private_key_passphrase'] ?? null,
            'sftp_host_fingerprint' => $envDefaults['host_fingerprint'] ?? null,
            'sftp_port' => $envDefaults['port'] ?? null,
        ];
    }

    protected function teamsWithProvider(string $providerCode): array
    {
        return DB::table((new TeamPaymentProvider())->getTable())
            ->where('provider_code', $providerCode)
            ->where('is_active', true)
            ->whereNotNull('team_id')
            ->distinct()
            ->pluck('team_id')
            ->all();
    }

    protected function logChannel(): string
    {
        return (string) config('finance-settlement.fetch.log_channel', config('logging.default'));
    }
}
