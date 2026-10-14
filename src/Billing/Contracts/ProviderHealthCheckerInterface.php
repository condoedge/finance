<?php

namespace Condoedge\Finance\Billing\Contracts;

use Condoedge\Finance\Billing\Core\PaymentOutcome;
use Condoedge\Finance\Billing\Core\ProviderHealthStatus;

interface ProviderHealthCheckerInterface
{
    /**
     * Convenience: is this provider currently usable (HEALTHY or DEGRADED)?
     * DOWN providers return false. Health is project-wide, one state per provider.
     */
    public function isHealthy(string $providerCode): bool;

    /**
     * Full status object. Lets callers distinguish DEGRADED from DOWN (e.g.,
     * the resolver puts degraded providers last in the chain rather than skipping).
     */
    public function status(string $providerCode): ProviderHealthStatus;

    /**
     * Record the outcome of a payment attempt. Called by PaymentProcessor.
     * Updates the snapshot table and applies thresholds (configured via
     * config('kompo-finance.health.*')) to transition state.
     */
    public function record(string $providerCode, PaymentOutcome $outcome): void;
}
