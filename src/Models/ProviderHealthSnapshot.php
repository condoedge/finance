<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Billing\Core\ProviderHealthState;
use Condoedge\Utils\Models\Model;

/**
 * Materialized provider health view. Written by DefaultProviderHealthChecker
 * after each payment attempt; read on every payment-form render. Saves a
 * scan of fin_payment_traces on the hot path. One row per provider, project-wide.
 */
class ProviderHealthSnapshot extends Model
{
    protected $table = 'fin_provider_health_snapshots';

    protected $fillable = [
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

    public static function for(string $providerCode): self
    {
        return static::firstOrNew(
            ['provider_code' => $providerCode],
            ['status' => ProviderHealthState::HEALTHY->value, 'consecutive_failures' => 0],
        );
    }
}
