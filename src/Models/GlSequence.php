<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Illuminate\Support\Facades\DB;

/**
 * GL Sequences Model
 * 
 * @property int $id
 * @property string $sequence_type
 * @property int $fiscal_year
 * @property int $next_number
 * @property int $team_id
 */
class GlSequence extends AbstractMainFinanceModel
{
    use HasIntegrityCheck;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_gl_sequences';
    
    protected $fillable = [
        'sequence_type',
        'fiscal_year',
        'next_number',
        'team_id',
    ];
    
    protected $casts = [
        'fiscal_year' => 'integer',
        'next_number' => 'integer',
    ];
    
    /**
     * Get next number for a sequence type in a fiscal year
     */
    public static function getNextNumber(int $teamId, string $sequenceType, int $fiscalYear): int
    {
        return DB::transaction(function () use ($teamId, $sequenceType, $fiscalYear) {
            $sequence = static::firstOrCreate(
                [
                    'team_id' => $teamId,
                    'sequence_type' => $sequenceType,
                    'fiscal_year' => $fiscalYear,
                ],
                [
                    'next_number' => 1,
                ]
            );
            
            $nextNumber = $sequence->next_number;
            $sequence->increment('next_number');
            
            return $nextNumber;
        });
    }
    
    /**
     * Reset sequence for a new fiscal year
     */
    public static function resetForNewFiscalYear(int $teamId, int $fiscalYear): void
    {
        static::where('team_id', $teamId)
              ->where('fiscal_year', $fiscalYear)
              ->delete();
    }
    
    /**
     * Get current number for a sequence
     */
    public static function getCurrentNumber(int $teamId, string $sequenceType, int $fiscalYear): int
    {
        $sequence = static::where('team_id', $teamId)
                          ->where('sequence_type', $sequenceType)
                          ->where('fiscal_year', $fiscalYear)
                          ->first();
        
        return $sequence ? $sequence->next_number - 1 : 0;
    }
    
    /**
     * Scope for team
     */
    public function scopeForTeam($query, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        return $query->where('team_id', $teamId);
    }
    
    /**
     * No calculated columns for this model
     */
    public static function columnsIntegrityCalculations()
    {
        return [];
    }
}
