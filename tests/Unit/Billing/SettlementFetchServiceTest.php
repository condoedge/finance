<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Billing\Settlement\Contracts\SettlementImportServiceInterface;
use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\SettlementFetcherInterface;
use Condoedge\Finance\Billing\Settlement\Fetch\FetchedFile;
use Condoedge\Finance\Billing\Settlement\Fetch\SettlementFetchService;
use Condoedge\Finance\Billing\Settlement\Fetch\SettlementSourceConfig;
use Condoedge\Finance\Billing\Settlement\SettlementImportResult;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\ProviderCredentials;
use Condoedge\Finance\Models\SettlementFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettlementFetchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('settlement-reports');
        config()->set('finance-settlement.fetch.local_disk', 'settlement-reports');
    }

    public function test_downloads_new_file_inserts_ledger_row_and_calls_importer(): void
    {
        $this->seedMonerisCredsFor(team: 7);

        $fetcher = $this->fetcherWith([
            $this->file('SP1_20260525.csv', 'csv-body-1'),
        ]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $outcomes = $service->run('moneris', teamId: 7);

        $this->assertCount(1, $outcomes);
        $this->assertEquals(1, $outcomes[0]->filesDownloaded);
        $this->assertEquals(1, $outcomes[0]->filesImported);

        $row = SettlementFile::findFor(7, 'moneris', 'SP1_20260525.csv');
        $this->assertNotNull($row);
        $this->assertNotNull($row->imported_at);
        Storage::disk('settlement-reports')->assertExists($row->local_path);
        $this->assertCount(1, $importer->calls);
    }

    public function test_run_is_idempotent_second_run_imports_nothing(): void
    {
        $this->seedMonerisCredsFor(team: 7);
        $fetcher = $this->fetcherWith([$this->file('A.csv', 'a')]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $service->run('moneris', teamId: 7);
        $second = $service->run('moneris', teamId: 7);

        $this->assertEquals(0, $second[0]->filesDownloaded);
        $this->assertEquals(0, $second[0]->filesImported);
        $this->assertEquals(1, $second[0]->filesSkipped);
        $this->assertCount(1, $importer->calls, 'Importer must not be re-invoked for already-imported files');
    }

    public function test_one_team_failure_does_not_block_others(): void
    {
        $this->seedMonerisCredsFor(team: 11);
        $this->seedMonerisCredsFor(team: 22);

        $fetcher = new class implements SettlementFetcherInterface {
            public function providerCode(): string { return 'moneris'; }
            public function listAvailable(SettlementSourceConfig $source, ?\DateTimeImmutable $since = null): iterable
            {
                if ($source->teamId === 11) {
                    throw new \RuntimeException('SFTP auth failed');
                }
                yield new FetchedFile('B.csv', 3, new \DateTimeImmutable(), 'B.csv');
            }
            public function downloadTo(SettlementSourceConfig $source, FetchedFile $file, string $localDisk, string $localPath): string
            {
                Storage::disk($localDisk)->put($localPath, 'body');
                return hash('sha256', 'body');
            }
        };

        $importer = $this->capturingImporter();
        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);

        $outcomes = $service->run('moneris');
        $byTeam = [];
        foreach ($outcomes as $o) { $byTeam[$o->teamId] = $o; }

        $this->assertArrayHasKey(11, $byTeam);
        $this->assertArrayHasKey(22, $byTeam);
        $this->assertNotNull($byTeam[11]->error);
        $this->assertNull($byTeam[22]->error);
        $this->assertEquals(1, $byTeam[22]->filesImported);
    }

    public function test_missing_credentials_skips_team_with_error_outcome(): void
    {
        // No creds row for team 99.
        $fetcher = $this->fetcherWith([$this->file('X.csv', 'x')]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $outcomes = $service->run('moneris', teamId: 99);

        $this->assertCount(1, $outcomes);
        $this->assertEquals('no-credentials', $outcomes[0]->error);
        $this->assertCount(0, $importer->calls);
    }

    public function test_dry_run_does_not_download_or_import(): void
    {
        $this->seedMonerisCredsFor(team: 7);
        $fetcher = $this->fetcherWith([$this->file('A.csv', 'a')]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $outcome = $service->run('moneris', teamId: 7, dryRun: true)[0];

        $this->assertEquals(0, $outcome->filesDownloaded);
        $this->assertEquals(0, $outcome->filesImported);
        $this->assertEquals(1, $outcome->filesSkipped);
        $this->assertCount(0, $importer->calls);
        $this->assertNull(SettlementFile::findFor(7, 'moneris', 'A.csv'));
    }

    public function test_content_duplicate_with_different_name_is_skipped(): void
    {
        $this->seedMonerisCredsFor(team: 7);

        $fetcher = $this->fetcherWith([
            $this->file('original.csv', 'identical-body'),
            $this->file('renamed.csv', 'identical-body'),
        ]);
        $importer = $this->capturingImporter();

        $service = new SettlementFetchService(['moneris' => $fetcher], $importer);
        $outcome = $service->run('moneris', teamId: 7)[0];

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

    protected function seedMonerisCredsFor(int $team): void
    {
        $creds = new ProviderCredentials();
        $creds->team_id = $team;
        $creds->provider_code = 'moneris';
        $creds->is_test = false;
        $creds->credentials = [
            'store_id' => 'STORE_' . $team,
            'api_token' => 'token',
            'sftp_host' => 'reports.moneris.test',
            'sftp_username' => 'tenant' . $team,
            'sftp_private_key' => '-----BEGIN KEY-----fake-----END KEY-----',
            'sftp_host_fingerprint' => 'SHA256:fake',
            'sftp_remote_path' => '/',
        ];
        $creds->save();

        // Mark team as having moneris enabled so the orchestrator's enumeration
        // picks it up when teamId is not pinned.
        DB::table('fin_team_payment_providers')->insert([
            'team_id' => $team,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'provider_code' => 'moneris',
            'priority' => 1,
            'is_active' => true,
            'mode' => 'single',
        ]);
    }
}
