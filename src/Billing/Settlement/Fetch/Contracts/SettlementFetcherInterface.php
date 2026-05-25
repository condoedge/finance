<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch\Contracts;

use Condoedge\Finance\Billing\Settlement\Fetch\FetchedFile;
use Condoedge\Finance\Billing\Settlement\Fetch\SettlementSourceConfig;

/**
 * Per-provider strategy for retrieving settlement reports from wherever the
 * provider publishes them. Implementations are stateless and receive their
 * remote-source configuration on each call so the same instance can serve
 * many tenants.
 */
interface SettlementFetcherInterface
{
    /**
     * Return the provider code this fetcher handles (e.g. "moneris").
     */
    public function providerCode(): string;

    /**
     * Connect, list the remote location, and return one descriptor per file
     * that the caller should consider for download. The fetcher itself does
     * not consult the ledger; the orchestrator decides which files to skip.
     *
     * @return iterable<FetchedFile>
     */
    public function listAvailable(SettlementSourceConfig $source, ?\DateTimeImmutable $since = null): iterable;

    /**
     * Stream the remote file to the given local Flysystem disk path. The
     * destination disk is owned by the caller, not the fetcher. Returns the
     * sha256 of the downloaded content.
     */
    public function downloadTo(SettlementSourceConfig $source, FetchedFile $file, string $localDisk, string $localPath): string;
}
