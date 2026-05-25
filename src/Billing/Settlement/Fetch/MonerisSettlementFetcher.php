<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\RemoteFilesystemFactoryInterface;
use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\SettlementFetcherInterface;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;

/**
 * Lists and downloads Moneris settlement reports from an SFTP filesystem
 * supplied by the factory. The SFTP wiring lives in the factory; the fetcher
 * is pure file-listing logic so it can be exercised with an in-memory adapter.
 */
class MonerisSettlementFetcher implements SettlementFetcherInterface
{
    public function __construct(
        protected RemoteFilesystemFactoryInterface $filesystemFactory,
    ) {
    }

    public function providerCode(): string
    {
        return 'moneris';
    }

    public function listAvailable(SettlementSourceConfig $source, ?\DateTimeImmutable $since = null): iterable
    {
        $fs = $this->filesystemFactory->make($source);
        $cutoff = $since ?? new \DateTimeImmutable('-' . max(1, $source->lookbackDays) . ' days');
        $cutoffTs = $cutoff->getTimestamp();

        foreach ($fs->listContents('', false) as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $path = $entry->path();
            if (!str_ends_with(strtolower($path), '.csv')) {
                continue;
            }

            $modified = $this->safeLastModified($fs, $path);
            if ($modified !== null && $modified < $cutoffTs) {
                continue;
            }

            $size = $this->safeFileSize($fs, $path);

            yield new FetchedFile(
                remoteFilename: basename($path),
                remoteSize: $size,
                remoteModifiedAt: (new \DateTimeImmutable())->setTimestamp($modified ?? time()),
                remotePath: $path,
            );
        }
    }

    public function downloadTo(SettlementSourceConfig $source, FetchedFile $file, string $localDisk, string $localPath): string
    {
        $fs = $this->filesystemFactory->make($source);
        $stream = $fs->readStream($file->remotePath);

        try {
            Storage::disk($localDisk)->writeStream($localPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $content = Storage::disk($localDisk)->get($localPath);
        return hash('sha256', (string) $content);
    }

    private function safeLastModified(Filesystem $fs, string $path): ?int
    {
        try {
            return $fs->lastModified($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeFileSize(Filesystem $fs, string $path): int
    {
        try {
            return $fs->fileSize($path);
        } catch (\Throwable) {
            return 0;
        }
    }
}
