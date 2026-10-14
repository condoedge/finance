<?php

namespace Condoedge\Finance\Billing\Settlement\Fetch;

/**
 * Resolved remote-source configuration for a fetch. Built by the orchestrator
 * from the provider's env config, then handed to the per-provider fetcher.
 *
 * For SFTP providers `auth` carries host/username/private_key/host_fingerprint;
 * future non-SFTP providers can reuse this same DTO with different `auth` keys.
 */
class SettlementSourceConfig
{
    public function __construct(
        public readonly string $providerCode,
        public readonly string $remotePath,
        public readonly array $auth,
        public readonly int $lookbackDays = 30,
    ) {
    }

    public function authValue(string $key): ?string
    {
        $value = $this->auth[$key] ?? null;
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function requireAuth(string $key): string
    {
        $value = $this->authValue($key);
        if ($value === null) {
            throw new \RuntimeException(sprintf(
                'Settlement source for %s is missing required auth field "%s".',
                $this->providerCode,
                $key,
            ));
        }
        return $value;
    }
}
