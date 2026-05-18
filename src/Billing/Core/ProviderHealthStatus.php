<?php

namespace Condoedge\Finance\Billing\Core;

enum ProviderHealthState: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case DOWN = 'down';
}

/**
 * Snapshot of a provider's recent reliability. Driven by recorded payment outcomes
 * (see DefaultProviderHealthChecker). Used by the resolver to order the fallback
 * chain and by the pre-form gate to decide whether to render the form at all.
 */
final class ProviderHealthStatus
{
    public function __construct(
        public readonly string $providerCode,
        public readonly ?int $teamId,
        public readonly ProviderHealthState $state,
        public readonly int $consecutiveFailures = 0,
        public readonly ?\DateTimeInterface $lastFailureAt = null,
        public readonly ?\DateTimeInterface $lastSuccessAt = null,
    ) {
    }

    public function isHealthy(): bool
    {
        return $this->state === ProviderHealthState::HEALTHY;
    }

    public function isUsable(): bool
    {
        return $this->state !== ProviderHealthState::DOWN;
    }

    public static function healthy(string $providerCode, ?int $teamId = null): self
    {
        return new self($providerCode, $teamId, ProviderHealthState::HEALTHY);
    }
}
