<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\RemoteFilesystemFactoryInterface;
use Condoedge\Finance\Billing\Settlement\Fetch\MonerisSettlementFetcher;
use Condoedge\Finance\Billing\Settlement\Fetch\SettlementSourceConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Tests\TestCase;

class MonerisSettlementFetcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_csv_files_within_lookback_window(): void
    {
        $adapter = new InMemoryFilesystemAdapter();
        $fs = new Filesystem($adapter);
        $fs->write('SP1_20260520.csv', "header\nrow1");
        $fs->write('SP1_20260510.csv', "header\nrow1");
        $fs->write('README.txt', 'ignore me');

        $fetcher = new MonerisSettlementFetcher($this->factoryReturning($fs));
        $source = $this->source(lookbackDays: 30);

        $files = iterator_to_array($fetcher->listAvailable($source));

        $names = array_map(fn ($f) => $f->remoteFilename, $files);
        sort($names);
        $this->assertEquals(['SP1_20260510.csv', 'SP1_20260520.csv'], $names);
    }

    public function test_lookback_filters_files_older_than_threshold(): void
    {
        $adapter = new InMemoryFilesystemAdapter();
        $fs = new Filesystem($adapter);
        $fs->write('recent.csv', 'a');

        $fetcher = new MonerisSettlementFetcher($this->factoryReturning($fs));
        $source = $this->source(lookbackDays: 0);

        $files = iterator_to_array($fetcher->listAvailable($source, new \DateTimeImmutable('+1 day')));

        $this->assertEmpty($files, 'A "since" newer than every file should yield no results');
    }

    public function test_download_writes_local_file_and_returns_sha256(): void
    {
        $adapter = new InMemoryFilesystemAdapter();
        $fs = new Filesystem($adapter);
        $content = "Transaction Number,Fee Amount,Transaction Amount\nABC123,0.30,10.00";
        $fs->write('SP1_20260525.csv', $content);

        Storage::fake('settlement-reports');

        $fetcher = new MonerisSettlementFetcher($this->factoryReturning($fs));
        $source = $this->source();
        $files = iterator_to_array($fetcher->listAvailable($source));
        $file = $files[0];

        $sha = $fetcher->downloadTo($source, $file, 'settlement-reports', 'moneris/7/SP1_20260525.csv');

        $this->assertEquals(hash('sha256', $content), $sha);
        Storage::disk('settlement-reports')->assertExists('moneris/7/SP1_20260525.csv');
        $this->assertEquals($content, Storage::disk('settlement-reports')->get('moneris/7/SP1_20260525.csv'));
    }

    public function test_provider_code_is_moneris(): void
    {
        $fetcher = new MonerisSettlementFetcher($this->factoryReturning(new Filesystem(new InMemoryFilesystemAdapter())));
        $this->assertEquals('moneris', $fetcher->providerCode());
    }

    protected function factoryReturning(Filesystem $fs): RemoteFilesystemFactoryInterface
    {
        return new class ($fs) implements RemoteFilesystemFactoryInterface {
            public function __construct(private Filesystem $fs) {}
            public function make(SettlementSourceConfig $source): Filesystem { return $this->fs; }
        };
    }

    protected function source(int $lookbackDays = 30): SettlementSourceConfig
    {
        return new SettlementSourceConfig(
            teamId: 7,
            providerCode: 'moneris',
            remotePath: '/',
            auth: [
                'sftp_host' => 'reports.moneris.test',
                'sftp_username' => 'tenant7',
                'sftp_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nfake\n-----END OPENSSH PRIVATE KEY-----",
                'sftp_host_fingerprint' => 'SHA256:fake',
            ],
            lookbackDays: $lookbackDays,
        );
    }
}
