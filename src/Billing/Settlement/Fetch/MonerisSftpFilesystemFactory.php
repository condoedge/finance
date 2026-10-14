<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\RemoteFilesystemFactoryInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

/**
 * Production filesystem builder for Moneris Merchant Direct SFTP. Reads the
 * private key from disk (`auth.sftp_private_key_path`). Host fingerprint is
 * mandatory — without it the connection refuses to open, blocking silent MITM.
 */
class MonerisSftpFilesystemFactory implements RemoteFilesystemFactoryInterface
{
    public function make(SettlementSourceConfig $source): Filesystem
    {
        $host = $source->requireAuth('sftp_host');
        $username = $source->requireAuth('sftp_username');
        $fingerprint = $source->requireAuth('sftp_host_fingerprint');
        $port = (int) ($source->authValue('sftp_port') ?? 22);

        $privateKey = $source->requireAuth('sftp_private_key_path');

        $connection = new SftpConnectionProvider(
            host: $host,
            username: $username,
            password: null,
            privateKey: $privateKey,
            passphrase: $source->authValue('sftp_private_key_passphrase'),
            port: $port,
            useAgent: false,
            timeout: 30,
            maxTries: 2,
            hostFingerprint: $fingerprint,
        );

        return new Filesystem(new SftpAdapter($connection, $source->remotePath));
    }
}
