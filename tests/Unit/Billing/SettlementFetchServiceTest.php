<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Billing\Settlement\Contracts\SettlementImportServiceInterface;
use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\SettlementFetcherInterface;
use Condoedge\Finance\Billing\Settlement\Fetch\FetchedFile;
use Condoedge\Finance\Billing\Settlement\Fetch\SettlementFetchService;
use Condoedge\Finance\Billing\Settlement\Fetch\SettlementSourceConfig;
use Condoedge\Finance\Billing\Settlement\SettlementImportResult;
use Condoedge\Finance\Models\SettlementFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettlementFetchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake('settlement-reports');
        config()->set('finance-settlement.fetch.local_disk', 'settlement-reports');
    }

    public function test_downloads_new_file_inserts_ledger_row_and_calls_importer(): void
    {
        $this->configureMonerisSftp();

        $fetcher = $this->fetcherWith([
            $this->file('SP1_20260525.csv', 'csv-body-1'),
        ]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $outcome = $service->run('moneris');

        $this->assertEquals(1, $outcome->filesDownloaded);
        $this->assertEquals(1, $outcome->filesImported);

        $row = SettlementFile::findFor('moneris', 'SP1_20260525.csv');
        $this->assertNotNull($row);
        $this->assertNotNull($row->imported_at);
        Storage::disk('settlement-reports')->assertExists($row->local_path);
        $this->assertCount(1, $importer->calls);
    }

    public function test_run_is_idempotent_second_run_imports_nothing(): void
    {
        $this->configureMonerisSftp();
        $fetcher = $this->fetcherWith([$this->file('A.csv', 'a')]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $service->run('moneris');
        $second = $service->run('moneris');

        $this->assertEquals(0, $second->filesDownloaded);
        $this->assertEquals(0, $second->filesImported);
        $this->assertEquals(1, $second->filesSkipped);
        $this->assertCount(1, $importer->calls, 'Importer must not be re-invoked for already-imported files');
    }

    public function test_a_fetch_failure_is_reported_not_thrown(): void
    {
        $this->configureMonerisSftp();

        $fetcher = new class implements SettlementFetcherInterface {
            public function providerCode(): string { return 'moneris'; }
            public function listAvailable(SettlementSourceConfig $source, ?\DateTimeImmutable $since = null): iterable
            {
                throw new \RuntimeException('SFTP auth failed');
            }
            public function downloadTo(SettlementSourceConfig $source, FetchedFile $file, string $localDisk, string $localPath): string
            {
                return '';
            }
        };

        $importer = $this->capturingImporter();
        $outcome = (new SettlementFetchService(['moneris' => $fetcher], $importer))->run('moneris');

        $this->assertEquals('SFTP auth failed', $outcome->error);
        $this->assertCount(0, $importer->calls);
    }

    public function test_missing_sftp_config_returns_no_credentials(): void
    {
        $fetcher = $this->fetcherWith([$this->file('X.csv', 'x')]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $outcome = $service->run('moneris');

        $this->assertEquals('no-credentials', $outcome->error);
        $this->assertCount(0, $importer->calls);
    }

    public function test_dry_run_does_not_download_or_import(): void
    {
        $this->configureMonerisSftp();
        $fetcher = $this->fetcherWith([$this->file('A.csv', 'a')]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $outcome = $service->run('moneris', dryRun: true);

        $this->assertEquals(0, $outcome->filesDownloaded);
        $this->assertEquals(0, $outcome->filesImported);
        $this->assertEquals(1, $outcome->filesSkipped);
        $this->assertCount(0, $importer->calls);
        $this->assertNull(SettlementFile::findFor('moneris', 'A.csv'));
    }

    public function test_content_duplicate_with_different_name_is_skipped(): void
    {
        $this->configureMonerisSftp();

        $fetcher = $this->fetcherWith([
            $this->file('original.csv', 'identical-body'),
            $this->file('renamed.csv', 'identical-body'),
        ]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $outcome = $service->run('moneris');

        $this->assertEquals(1, $outcome->filesDownloaded);
        $this->assertEquals(1, $outcome->filesImported);
        $this->assertEquals(1, $outcome->filesSkipped, 'The renamed duplicate must not double-import');
        $this->assertCount(1, $importer->calls);
    }

    /**
     * Build a fetcher whose listing returns the given files and whose download
     * writes the configured body to the local disk.
     *
     * @param array<int, array{file: FetchedFile, body: string}> $files
     */
    protected function fetcherWith(array $files): SettlementFetcherInterface
    {
        return new class ($files) implements SettlementFetcherInterface {
            public function __construct(private array $files) {}
            public function providerCode(): string { return 'moneris'; }
            public function listAvailable(SettlementSourceConfig $source, ?\DateTimeImmutable $since = null): iterable
            {
                foreach ($this->files as $entry) { yield $entry['file']; }
            }
            public function downloadTo(SettlementSourceConfig $source, FetchedFile $file, string $localDisk, string $localPath): string
            {
                $body = '';
                foreach ($this->files as $entry) {
                    if ($entry['file']->remoteFilename === $file->remoteFilename) {
                        $body = $entry['body'];
                        break;
                    }
                }
                Storage::disk($localDisk)->put($localPath, $body);
                return hash('sha256', $body);
            }
        };
    }

    protected function file(string $name, string $body): array
    {
        return [
            'file' => new FetchedFile($name, strlen($body), new \DateTimeImmutable(), $name),
            'body' => $body,
        ];
    }

    protected function capturingImporter(): SettlementImportServiceInterface
    {
        return new class implements SettlementImportServiceInterface {
            public array $calls = [];
            public function import(string $providerCode, string $filePath): SettlementImportResult
            {
                $this->calls[] = ['provider' => $providerCode, 'path' => $filePath];
                return new SettlementImportResult($providerCode, 1, 1, 0, []);
            }
        };
    }

    protected function configureMonerisSftp(): void
    {
        config()->set('finance-settlement.providers.moneris.sftp', [
            'host' => 'reports.moneris.test',
            'username' => 'sisc',
            'private_key_path' => '/keys/moneris',
            'host_fingerprint' => 'SHA256:fake',
            'remote_path' => '/',
        ]);
    }
}
