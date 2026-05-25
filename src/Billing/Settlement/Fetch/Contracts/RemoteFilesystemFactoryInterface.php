<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch\Contracts;

use Condoedge\Finance\Billing\Settlement\Fetch\SettlementSourceConfig;
use League\Flysystem\Filesystem;

/**
 * Builds a Flysystem filesystem pointing at one team's remote settlement
 * source. Split from the fetcher so unit tests can swap an in-memory adapter
 * in place of a live SFTP connection.
 */
interface RemoteFilesystemFactoryInterface
{
    public function make(SettlementSourceConfig $source): Filesystem;
}
