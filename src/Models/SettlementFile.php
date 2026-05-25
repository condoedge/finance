<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Billing\Settlement\SettlementImportResult;
use Condoedge\Utils\Models\Model;

/**
 * Idempotency ledger for fetched provider settlement reports. One row per
 * (team, provider, remote filename) and per (team, provider, content sha256).
 * `imported_at` distinguishes "downloaded but not yet imported" from "done".
 */
class SettlementFile extends Model
{
    protected $table = 'fin_settlement_files';

    protected $fillable = [
        'team_id',
        'provider_code',
        'remote_filename',
        'local_path',
        'remote_size',
        'sha256',
        'fetched_at',
        'imported_at',
        'import_result_json',
        'last_error',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'remote_size' => 'integer',
        'fetched_at' => 'datetime',
        'imported_at' => 'datetime',
        'import_result_json' => 'array',
    ];

    public static function findFor(int $teamId, string $providerCode, string $filename): ?self
    {
        return static::query()
            ->where('team_id', $teamId)
            ->where('provider_code', $providerCode)
            ->where('remote_filename', $filename)
            ->first();
    }

    public static function findBySha(int $teamId, string $providerCode, string $sha256): ?self
    {
        return static::query()
            ->where('team_id', $teamId)
            ->where('provider_code', $providerCode)
            ->where('sha256', $sha256)
            ->first();
    }

    public static function markFetched(
        int $teamId,
        string $providerCode,
        string $filename,
        string $localPath,
        int $size,
        string $sha256,
    ): self {
        $row = new self();
        $row->team_id = $teamId;
        $row->provider_code = $providerCode;
        $row->remote_filename = $filename;
        $row->local_path = $localPath;
        $row->remote_size = $size;
        $row->sha256 = $sha256;
        $row->fetched_at = now();
        $row->save();

        return $row;
    }

    public function markImported(SettlementImportResult $result): void
    {
        $this->imported_at = now();
        $this->import_result_json = [
            'providerCode' => $result->providerCode,
            'rowsParsed' => $result->rowsParsed,
            'matched' => $result->matched,
            'unmatched' => $result->unmatched,
            'unmatchedRefs' => $result->unmatchedRefs,
        ];
        $this->last_error = null;
        $this->save();
    }

    public function markFailed(string $message): void
    {
        $this->last_error = $message;
        $this->save();
    }
}
