<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

/**
 * Encrypted per-team provider credentials. Per design §2.2: credentials live
 * in their own table so they can be rotated independently of which provider
 * a team has enabled. APP_KEY-encrypted via Laravel's encrypted:array cast.
 */
class ProviderCredentials extends Model
{
    protected $table = 'fin_provider_credentials';

    protected $fillable = [
        'team_id',
        'provider_code',
        'credentials',
        'is_test',
        'last_rotated_at',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_test' => 'boolean',
        'last_rotated_at' => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->credentials ?? []);
    }

    /**
     * Convenience lookup: per-team credentials first, then global default,
     * then null. Resolver calls this when building each chain entry.
     */
    public static function lookup(?int $teamId, string $providerCode, bool $isTest = false): ?self
    {
        if ($teamId !== null) {
            $perTeam = static::query()
                ->where('team_id', $teamId)
                ->where('provider_code', $providerCode)
                ->where('is_test', $isTest)
                ->first();
            if ($perTeam) {
                return $perTeam;
            }
        }

        return static::query()
            ->whereNull('team_id')
            ->where('provider_code', $providerCode)
            ->where('is_test', $isTest)
            ->first();
    }
}
