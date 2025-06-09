<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

/**
 * Account Segment Value Model
 * Stores individual segment values (segment_type = 2)
 * 
 * @property int $id
 * @property int $team_id
 * @property int $segment_number Position of the segment (1, 2, 3)
 * @property string $segment_value Actual code value ('04', '205', '1105')
 * @property string $segment_description Description ('project04', 'Construction', 'Material expense')
 * @property bool $is_active
 */
class AccountSegmentValue extends Model
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_account_segment_values';
    
    protected $fillable = [
        'team_id',
        'segment_number',
        'segment_value',
        'segment_description',
        'is_active',
    ];
    
    protected $casts = [
        'segment_number' => 'integer',
        'is_active' => 'boolean',
    ];
    
    /**
     * Get the segment definition for this value
     */
    public function segmentDefinition()
    {
        return $this->belongsTo(AccountSegmentDefinition::class, 'segment_number', 'segment_number')
            ->where('team_id', $this->team_id);
    }
    
    /**
     * Get all segment values for a specific segment number and team
     */
    public static function getForSegment($segmentNumber, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        
        return static::where('team_id', $teamId)
            ->where('segment_number', $segmentNumber)
            ->where('is_active', true)
            ->orderBy('segment_value')
            ->get();
    }
    
    /**
     * Get segment value options for dropdown
     */
    public static function getOptionsForSegment($segmentNumber, $teamId = null)
    {
        return static::getForSegment($segmentNumber, $teamId)
            ->pluck('segment_description', 'segment_value');
    }
    
    /**
     * Validate that a segment value exists for the team
     */
    public static function validateSegmentValue($segmentNumber, $segmentValue, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        
        return static::where('team_id', $teamId)
            ->where('segment_number', $segmentNumber)
            ->where('segment_value', $segmentValue)
            ->where('is_active', true)
            ->exists();
    }
    
    /**
     * Parse account ID and get segment values
     */
    public static function parseAccountId($accountId, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        
        // Get separator from segment definitions
        $definition = AccountSegmentDefinition::where('team_id', $teamId)->first();
        if (!$definition) {
            return [];
        }
        
        $separator = $definition->segment_separator;
        $segments = explode($separator, $accountId);
        
        $result = [];
        foreach ($segments as $index => $value) {
            $segmentNumber = $index + 1;
            $segmentValue = static::where('team_id', $teamId)
                ->where('segment_number', $segmentNumber)
                ->where('segment_value', $value)
                ->first();
            
            if ($segmentValue) {
                $result[] = [
                    'segment_number' => $segmentNumber,
                    'segment_value' => $value,
                    'segment_description' => $segmentValue->segment_description,
                ];
            }
        }
        
        return $result;
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
     * Scope for active values
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
