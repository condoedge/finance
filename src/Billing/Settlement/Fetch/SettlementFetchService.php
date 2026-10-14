<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

use Condoedge\Finance\Billing\Settlement\Contracts\SettlementImportServiceInterface;
use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\SettlementFetcherInterface;
use Condoedge\Finance\Models\SettlementFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrator: reads the provider's env SFTP source, drives the fetcher,
 * persists the ledger, calls the import service. One merchant account per project.
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
     * Run a fetch+import cycle for the provider's configured source.
     */
    public function run(string $providerCode, ?\DateTimeImmutable $since = null, bool $dryRun = false): FetchOutcome
    {
        $fetcher = $this->fetchers[$providerCode] ?? null;
        if ($fetcher === null) {
            throw new \InvalidArgumentException("No fetcher registered for provider '{$providerCode}'.");
        }

        try {
            $source = $this->resolveSource($providerCode);
            if ($source === null) {
                return new FetchOutcome($providerCode, error: 'no-credentials');
            }

            $downloaded = 0;
            $imported = 0;
            $skipped = 0;

            $localDisk = (string) config('finance-settlement.fetch.local_disk', 'settlement-reports');

            foreach ($fetcher->listAvailable($source, $since) as $file) {
                $existing = SettlementFile::findFor($providerCode, $file->remoteFilename);

                if ($existing && $existing->imported_at !== null) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $skipped++;
                    continue;
                }

                if (!$existing) {
                    $localPath = sprintf('%s/%s', $providerCode, $file->remoteFilename);
                    $sha = $fetcher->downloadTo($source, $file, $localDisk, $localPath);

                    if (SettlementFile::findBySha($providerCode, $sha)) {
                        // Content-level duplicate: another filename had this exact body.
                        Storage::disk($localDisk)->delete($localPath);
                        $skipped++;
                        continue;
                    }

                    $existing = SettlementFile::markFetched(
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

            return new FetchOutcome(
                providerCode: $providerCode,
                filesDownloaded: $downloaded,
                filesImported: $imported,
                filesSkipped: $skipped,
            );
        } catch (\Throwable $e) {
            Log::channel($this->logChannel())->error(
                "[settlement-fetch] {$providerCode} failed: " . $e->getMessage(),
                ['exception' => $e],
            );
            return new FetchOutcome(
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
                "[settlement-fetch] import failed for {$providerCode} / file {$row->remote_filename}: " . $e->getMessage(),
            );
        }
    }

    protected function resolveSource(string $providerCode): ?SettlementSourceConfig
    {
        $env = (array) config("finance-settlement.providers.{$providerCode}.sftp", []);

        $auth = array_filter([
            'sftp_host' => $env['host'] ?? null,
            'sftp_username' => $env['username'] ?? null,
            'sftp_private_key_path' => $env['private_key_path'] ?? null,
            'sftp_private_key_passphrase' => $env['private_key_passphrase'] ?? null,
            'sftp_host_fingerprint' => $env['host_fingerprint'] ?? null,
            'sftp_port' => $env['port'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        foreach (['sftp_host', 'sftp_username', 'sftp_host_fingerprint', 'sftp_private_key_path'] as $key) {
            if (!isset($auth[$key])) {
                return null;
            }
        }

        return new SettlementSourceConfig(
            providerCode: $providerCode,
            remotePath: $env['remote_path'] ?? '/',
            auth: $auth,
            lookbackDays: (int) config('finance-settlement.fetch.lookback_days', 30),
        );
    }

    protected function logChannel(): string
    {
        return (string) config('finance-settlement.fetch.log_channel', config('logging.default'));
    }
}
