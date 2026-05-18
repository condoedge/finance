<?php

namespace Condoedge\Finance\Billing\Core\Health;

use Condoedge\Finance\Billing\Contracts\ProviderHealthCheckerInterface;
use Condoedge\Finance\Billing\Core\PaymentOutcome;
use Condoedge\Finance\Billing\Core\ProviderHealthState;
use Condoedge\Finance\Billing\Core\ProviderHealthStatus;
use Condoedge\Finance\Models\ProviderHealthSnapshot;

/**
 * Sliding-window health tracker. See design §5.
 *
 * Thresholds configured via config('kompo-finance.health.*'):
 *   - failures_to_degrade (default 3 consecutive)
 *   - failures_to_down    (default 8 consecutive)
 *   - window_seconds      (default 600s) — used for half-open recovery
 *   - recovery_successes  (default 2)    — to flip degraded→healthy
 *
 * Critical rule: PERMANENT errors (card declined) do NOT increment failure
 * counters — that's a customer issue, not a provider outage. Only TRANSIENT,
 * NETWORK, AUTH, UNKNOWN affect health.
 */
class DefaultProviderHealthChecker implements ProviderHealthCheckerInterface
{
    public function isHealthy(string $providerCode, ?int $teamId = null): bool
    {
        return $this->status($providerCode, $teamId)->isUsable();
    }

    public function status(string $providerCode, ?int $teamId = null): ProviderHealthStatus
    {
        $snapshot = $this->snapshot($teamId, $providerCode);

        if (!$snapshot->exists) {
            return ProviderHealthStatus::healthy($providerCode, $teamId);
        }

        $state = $this->maybeHalfOpen($snapshot);

        return new ProviderHealthStatus(
            providerCode: $providerCode,
            teamId: $teamId,
            state: $state,
            consecutiveFailures: $snapshot->consecutive_failures ?? 0,
            lastFailureAt: $snapshot->last_failure_at,
            lastSuccessAt: $snapshot->last_success_at,
        );
    }

    public function record(string $providerCode, ?int $teamId, PaymentOutcome $outcome): void
    {
        $snapshot = $this->snapshot($teamId, $providerCode);

        if ($outcome->success) {
            $snapshot->consecutive_failures = 0;
            $snapshot->last_success_at = now();
            $snapshot->status = ProviderHealthState::HEALTHY->value;
            $snapshot->save();
            return;
        }

        // Permanent customer errors (card declined) do not reflect on provider health.
        if ($outcome->errorClassification && !$outcome->errorClassification->affectsHealth()) {
            return;
        }

        $snapshot->consecutive_failures = ($snapshot->consecutive_failures ?? 0) + 1;
        $snapshot->last_failure_at = now();
        $snapshot->status = $this->stateFromFailures($snapshot->consecutive_failures)->value;
        $snapshot->save();
    }

    private function snapshot(?int $teamId, string $providerCode): ProviderHealthSnapshot
    {
        return ProviderHealthSnapshot::for($teamId, $providerCode);
    }

    private function stateFromFailures(int $failures): ProviderHealthState
    {
        if ($failures >= (int) config('kompo-finance.health.failures_to_down', 8)) {
            return ProviderHealthState::DOWN;
        }

        if ($failures >= (int) config('kompo-finance.health.failures_to_degrade', 3)) {
            return ProviderHealthState::DEGRADED;
        }

        return ProviderHealthState::HEALTHY;
    }

    /**
     * Half-open circuit: if status=DOWN and last_failure is older than window,
     * promote to DEGRADED so the next request gets through. If it succeeds,
     * record() flips it back to HEALTHY. If it fails again, it returns to DOWN.
     */
    private function maybeHalfOpen(ProviderHealthSnapshot $snapshot): ProviderHealthState
    {
        $state = $snapshot->status instanceof ProviderHealthState
            ? $snapshot->status
            : ProviderHealthState::from($snapshot->status ?? 'healthy');

        if ($state !== ProviderHealthState::DOWN || !$snapshot->last_failure_at) {
            return $state;
        }

        $window = (int) config('kompo-finance.health.window_seconds', 600);
        if ($snapshot->last_failure_at->diffInSeconds(now()) > $window) {
            return ProviderHealthState::DEGRADED;
        }

        return $state;
    }
}
