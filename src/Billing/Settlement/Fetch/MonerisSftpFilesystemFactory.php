<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

use Condoedge\Finance\Billing\Settlement\Fetch\Contracts\RemoteFilesystemFactoryInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

/**
 * Production filesystem builder for Moneris Merchant Direct SFTP. Reads the
 * private key from disk (path in `auth.private_key_path`) or inline PEM
 * (`auth.private_key`). Host fingerprint is mandatory — without it the
 * connection refuses to open, blocking silent MITM.
 */
class MonerisSftpFilesystemFactory implements RemoteFilesystemFactoryInterface
{
    public function make(SettlementSourceConfig $source): Filesystem
    {
        $host = $source->requireAuth('sftp_host');
        $username = $source->requireAuth('sftp_username');
        $fingerprint = $source->requireAuth('sftp_host_fingerprint');
        $port = (int) ($source->authValue('sftp_port') ?? 22);

        $privateKey = $this->resolvePrivateKey($source);

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

    /**
     * Use the inline PEM if provided, otherwise dump the on-disk key path into
     * Flysystem (it accepts either). Inline keys are written to a tmp file
     * because phpseclib expects a path.
     */
    protected function resolvePrivateKey(SettlementSourceConfig $source): string
    {
        $inline = $source->authValue('sftp_private_key');
        $path = $source->authValue('sftp_private_key_path');

        if ($inline !== null) {
            $tmp = tempnam(sys_get_temp_dir(), 'mnrs_sftp_');
            file_put_contents($tmp, $inline);
            @chmod($tmp, 0600);
            return $tmp;
        }

        if ($path === null) {
            throw new \RuntimeException(sprintf(
                'Team %d / moneris SFTP source has neither sftp_private_key nor sftp_private_key_path.',
                $source->teamId,
            ));
        }

        return $path;
    }
}
