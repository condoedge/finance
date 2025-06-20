<?php

namespace Condoedge\Finance\Models;

use Condoedge\Utils\Models\Model;

/**
 * Account Segment Definition Model
 * 
 * Defines the structure of account segments (position, length, description)
 * Example: Position 1 = 'Parent Team' with length 2, Position 2 = 'Team' with length 2, etc.
 * 
 * @property int $id
 * @property string $segment_description Description like 'Parent Team', 'Team', 'Account'
 * @property int $segment_position Position of the segment (1, 2, 3)
 * @property int $segment_length Character length for this segment
 */
class AccountSegment extends Model
{
    protected $table = 'fin_account_segments';
    
    protected $fillable = [
        'segment_description',
        'segment_position', 
        'segment_length',
    ];
    
    protected $casts = [
        'segment_position' => 'integer',
        'segment_length' => 'integer',
    ];
    
    /**
     * Get all segment values for this segment definition
     */
    public function segmentValues()
    {
        return $this->hasMany(SegmentValue::class, 'segment_definition_id');
    }
    
    /**
     * Get active segment values for this segment definition
     */
    public function activeSegmentValues()
    {
        return $this->hasMany(SegmentValue::class, 'segment_definition_id')
            ->where('is_active', true)
            ->orderBy('segment_value');
    }
    
    /**
     * Scope to order by position
     */
    public function scopeOrderedByPosition($query)
    {
        return $query->orderBy('segment_position');
    }
    
    /**
     * Get segment definition by position
     */
    public static function getByPosition(int $position): ?self
    {
        return static::where('segment_position', $position)->first();
    }
    
    /**
     * Get all segment definitions ordered by position
     */
    public static function getAllOrdered(): \Illuminate\Support\Collection
    {
        return static::orderedByPosition()->get();
    }
    
    /**
     * Validate segment structure setup
     */
    public static function validateStructure(): bool
    {
        $segments = static::getAllOrdered();
        
        // Check for gaps in positions
        for ($i = 1; $i <= $segments->count(); $i++) {
            if (!$segments->contains('segment_position', $i)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get the account format mask
     * Example: "XX-XX-XXXX" for 3 segments with lengths 2, 2, 4
     */
    public static function getAccountFormatMask(): string
    {
        $segments = static::getAllOrdered();
        $format = [];
        
        foreach ($segments as $segment) {
            $format[] = str_repeat('X', $segment->segment_length);
        }
        
        return implode('-', $format);
    }
    
    /**
     * Get the last segment position (natural account)
     */
    public static function getLastPosition(): ?self
    {
        return static::orderedByPosition()->orderBy('segment_position', 'desc')->first();
    }
    
    /**
     * Check if segment has values
     */
    public function hasValues(): bool
    {
        return $this->segmentValues()->exists();
    }
    
    /**
     * Reorder segment positions to ensure they are sequential
     */
    public static function reorderPositions(): void
    {
        $segments = static::orderedByPosition()->get();
        $position = 1;
        
        foreach ($segments as $segment) {
            if ($segment->segment_position !== $position) {
                $segment->update(['segment_position' => $position]);
            }
            $position++;
        }
    }
}
