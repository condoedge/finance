<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
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
 * @property string|null $default_handler Handler type for automatic value generation
 * @property array|null $default_handler_config Configuration for the default handler
 */
class AccountSegment extends Model
{
    protected $table = 'fin_account_segments';
    
    // Removed fillable - using property assignment instead
    
    protected $casts = [
        'segment_position' => 'integer',
        'segment_length' => 'integer',
        'default_handler_config' => 'array',
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
    public static function getAllOrdered($with = []): \Illuminate\Support\Collection
    {
        return static::orderedByPosition()->with($with)->get();
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
                $segment->segment_position = $position;
                $segment->save();
            }
            $position++;
        }
    }

    public function deletable()
    {
        return true;
    }
    
    /**
     * Get the default handler enum instance
     */
    public function getDefaultHandlerEnumAttribute(): ?SegmentDefaultHandlerEnum
    {
        return $this->default_handler 
            ? SegmentDefaultHandlerEnum::tryFrom($this->default_handler)
            : null;
    }
    
    /**
     * Check if this segment has automated defaults
     */
    public function hasDefaultHandler(): bool
    {
        return $this->default_handler && 
               $this->default_handler !== SegmentDefaultHandlerEnum::MANUAL->value;
    }
    
    /**
     * Check if this segment requires configuration for its handler
     */
    public function requiresHandlerConfig(): bool
    {
        $handler = $this->default_handler_enum;
        return $handler ? $handler->requiresConfig() : false;
    }
    
    /**
     * Validate handler configuration
     */
    public function validateHandlerConfig(): array
    {
        if (!$this->default_handler_enum) {
            return [];
        }
        
        return $this->default_handler_enum->validateConfig($this->default_handler_config);
    }
}
