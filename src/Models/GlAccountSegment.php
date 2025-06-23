<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Traits\HasIntegrityCheck;
use Condoedge\Utils\Models\Model;

/**
 * GL Account Segments Model
 * 
 * Handles both segment structure definitions (type 1) and segment values (type 2)
 * 
 * @property int $id
 * @property int $segment_type 1=Structure Definition, 2=Account Segment Value
 * @property int $segment_number Position of segment (1, 2, 3, etc.)
 * @property string|null $segment_value Actual code value (e.g., "04", "205") - null for type 1
 * @property string $segment_description Description of segment or value
 * @property int $team_id
 */
class GlAccountSegment extends Model
{
    use HasIntegrityCheck;
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_gl_account_segments';
    
    protected $casts = [
        'segment_number' => 'integer',
    ];
    
    /**
     * Scopes
     */
    public function scopeForTeam($query, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        return $query->where('team_id', $teamId);
    }
    
    public function scopeStructureDefinitions($query)
    {
        return $query->where('segment_type', self::TYPE_STRUCTURE);
    }
    
    public function scopeSegmentValues($query)
    {
        return $query->where('segment_type', self::TYPE_VALUE);
    }
    
    public function scopeForSegmentNumber($query, int $segmentNumber)
    {
        return $query->where('segment_number', $segmentNumber);
    }
    
    public function scopeForSegmentValue($query, string $segmentValue)
    {
        return $query->where('segment_value', $segmentValue);
    }
    
    /**
     * Get segment structure for a team
     */
    public static function getSegmentStructureForTeam($teamId = null): \Illuminate\Support\Collection
    {
        $teamId = $teamId ?? currentTeamId();
        
        return static::forTeam($teamId)
            ->structureDefinitions()
            ->orderBy('segment_number')
            ->get();
    }
    
    /**
     * Get segment values for a specific segment number
     */
    public static function getSegmentValuesForNumber($segmentNumber, $teamId = null): \Illuminate\Support\Collection
    {
        $teamId = $teamId ?? currentTeamId();
        
        return static::forTeam($teamId)
            ->segmentValues()
            ->forSegmentNumber($segmentNumber)
            ->orderBy('segment_value')
            ->get();
    }
    
    /**
     * Validate if a segment value exists for a given segment number
     */
    public static function isValidSegmentValue(int $segmentNumber, string $segmentValue, $teamId = null): bool
    {
        $teamId = $teamId ?? currentTeamId();
        
        return static::forTeam($teamId)
            ->segmentValues()
            ->forSegmentNumber($segmentNumber)
            ->forSegmentValue($segmentValue)
            ->exists();
    }
    
    /**
     * Get complete account ID from segment values
     */
    public static function buildAccountId(array $segmentValues, $teamId = null): string
    {
        // Example: ['04', '205', '1105'] becomes '04-205-1105'
        return implode('-', $segmentValues);
    }
    
    /**
     * Parse account ID into segment values
     */
    public static function parseAccountId(string $accountId): array
    {
        return explode('-', $accountId);
    }
    
    /**
     * Get segment description for a specific value
     */
    public static function getSegmentDescription(int $segmentNumber, string $segmentValue, $teamId = null): ?string
    {
        $teamId = $teamId ?? currentTeamId();
        
        $segment = static::forTeam($teamId)
            ->segmentValues()
            ->forSegmentNumber($segmentNumber)
            ->forSegmentValue($segmentValue)
            ->first();
            
        return $segment?->segment_description;
    }
    
    /**
     * No calculated columns for segments
     */
    public static function columnsIntegrityCalculations()
    {
        return [];
    }
}
