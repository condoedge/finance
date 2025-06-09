<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

/**
 * Account Segment Definition Model
 * Defines the structure of account segments (segment_type = 1)
 * 
 * @property int $id
 * @property int $team_id
 * @property int $segment_number Position of the segment (1, 2, 3)
 * @property int $segment_length Character length for this segment
 * @property string $segment_description Description like 'Project', 'Activity', 'Account'
 * @property string $segment_separator Separator character (default '-')
 */
class AccountSegmentDefinition extends Model
{
    use \Condoedge\Utils\Models\Traits\BelongsToTeamTrait;
    
    protected $table = 'fin_account_segment_definitions';
    
    protected $fillable = [
        'team_id',
        'segment_number',
        'segment_length',
        'segment_description',
        'segment_separator',
    ];
    
    protected $casts = [
        'segment_number' => 'integer',
        'segment_length' => 'integer',
    ];
    
    /**
     * Get segment values for this definition
     */
    public function segmentValues()
    {
        return $this->hasMany(AccountSegmentValue::class, 'segment_number', 'segment_number')
            ->where('team_id', $this->team_id);
    }
    
    /**
     * Get the account format mask for this team
     */
    public static function getAccountFormatForTeam($teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        
        $definitions = static::where('team_id', $teamId)
            ->orderBy('segment_number')
            ->get();
        
        if ($definitions->isEmpty()) {
            return null;
        }
        
        $format = [];
        $separator = $definitions->first()->segment_separator;
        
        foreach ($definitions as $definition) {
            $format[] = str_repeat('X', $definition->segment_length);
        }
        
        return implode($separator, $format);
    }
    
    /**
     * Validate account format against team definitions
     */
    public static function validateAccountFormat($accountId, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        
        $definitions = static::where('team_id', $teamId)
            ->orderBy('segment_number')
            ->get();
        
        if ($definitions->isEmpty()) {
            return false;
        }
        
        $separator = $definitions->first()->segment_separator;
        $segments = explode($separator, $accountId);
        
        if (count($segments) !== $definitions->count()) {
            return false;
        }
        
        foreach ($definitions as $index => $definition) {
            if (strlen($segments[$index]) !== $definition->segment_length) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Scope for team
     */
    public function scopeForTeam($query, $teamId = null)
    {
        $teamId = $teamId ?? currentTeamId();
        return $query->where('team_id', $teamId);
    }
}
