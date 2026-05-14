<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Billing\Core\ProviderHealthState;
use Condoedge\Utils\Models\Model;

/**
 * Materialized provider health view. Written by DefaultProviderHealthChecker
 * after each payment attempt; read on every payment-form render. Saves a
 * scan of fin_payment_traces on the hot path.
 *
 * Per-team rows isolate teams with their own credentials; the team_id IS NULL
 * row represents global provider health (shared-credentials providers).
 */
class ProviderHealthSnapshot extends Model
{
    protected $table = 'fin_provider_health_snapshots';

    protected $fillable = [
        'team_id',
        'provider_code',
        'status',
        'consecutive_failures',
        'last_failure_at',
        'last_success_at',
    ];

    protected $casts = [
        'status' => ProviderHealthState::class,
        'consecutive_failures' => 'integer',
        'last_failure_at' => 'datetime',
        'last_success_at' => 'datetime',
    ];

    public static function for(?int $teamId, string $providerCode): self
    {
        return static::firstOrNew(
            ['team_id' => $teamId, 'provider_code' => $providerCode],
            ['status' => ProviderHealthState::HEALTHY->value, 'consecutive_failures' => 0],
        );
    }
}
