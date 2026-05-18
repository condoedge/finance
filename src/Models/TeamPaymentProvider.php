<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

/**
 * Per-team mapping of which provider handles which payment method. Replaces
 * the static config('kompo-finance.payment_method_providers') array. See
 * design §2.1 and audit §1.1.2.
 *
 * Resolver loads ordered rows for (team_id, payment_method_id) and walks them
 * as the fallback chain. mode=single means stop on first failure; mode=fallback
 * means try the next row.
 */
class TeamPaymentProvider extends Model
{
    protected $table = 'fin_team_payment_providers';

    public const MODE_SINGLE = 'single';
    public const MODE_FALLBACK = 'fallback';

    protected $fillable = [
        'team_id',
        'payment_method_id',
        'provider_code',
        'priority',
        'is_active',
        'mode',
        'credentials_id',
    ];

    protected $casts = [
        'payment_method_id' => PaymentMethodEnum::class,
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function credentials()
    {
        return $this->belongsTo(ProviderCredentials::class, 'credentials_id');
    }

    /**
     * Resolver hot path. Returns ordered active rows for the (team, method).
     * Per-team rows win; falls back to team_id IS NULL (global default rows)
     * when no team-scoped rows exist.
     */
    public static function chainFor(?int $teamId, PaymentMethodEnum $method)
    {
        $forTeam = static::query()
            ->where('payment_method_id', $method)
            ->where('is_active', true)
            ->where(function ($q) use ($teamId) {
                if ($teamId !== null) {
                    $q->where('team_id', $teamId);
                } else {
                    $q->whereNull('team_id');
                }
            })
            ->orderBy('priority', 'asc')
            ->get();

        if ($forTeam->isNotEmpty()) {
            return $forTeam;
        }

        // Team has no rows — fall back to global defaults (team_id IS NULL).
        if ($teamId !== null) {
            return static::query()
                ->whereNull('team_id')
                ->where('payment_method_id', $method)
                ->where('is_active', true)
                ->orderBy('priority', 'asc')
                ->get();
        }

        return $forTeam; // empty
    }
}
