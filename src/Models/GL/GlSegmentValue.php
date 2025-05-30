<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;

class GlSegmentValue extends AbstractMainFinanceModel
{
    protected $table = 'fin_gl_segment_values';
    protected $primaryKey = 'segment_value_id';

    protected $fillable = [
        'segment_type',
        'segment_number',
        'segment_value',
        'segment_description',
        'is_active'
    ];

    protected $casts = [
        'segment_type' => 'integer',
        'segment_number' => 'integer',
        'is_active' => 'boolean'
    ];

    // Constants for segment types
    const TYPE_STRUCTURE_DEFINITION = 1;
    const TYPE_SEGMENT_VALUE = 2;

    /**
     * Scope for structure definitions
     */
    public function scopeStructureDefinitions($query)
    {
        return $query->where('segment_type', self::TYPE_STRUCTURE_DEFINITION);
    }

    /**
     * Scope for segment values
     */
    public function scopeSegmentValues($query)
    {
        return $query->where('segment_type', self::TYPE_SEGMENT_VALUE);
    }

    /**
     * Scope for specific segment position
     */
    public function scopeForSegment($query, int $segmentNumber)
    {
        return $query->where('segment_number', $segmentNumber);
    }

    /**
     * Get structure definitions
     */
    public static function getStructureDefinitions()
    {
        return static::structureDefinitions()
                    ->where('is_active', true)
                    ->orderBy('segment_number')
                    ->get();
    }

    /**
     * Get segment values for a specific segment
     */
    public static function getSegmentValues(int $segmentNumber)
    {
        return static::segmentValues()
                    ->forSegment($segmentNumber)
                    ->where('is_active', true)
                    ->orderBy('segment_value')
                    ->get();
    }

    /**
     * Get segment description by value and position
     */
    public static function getSegmentDescription(int $segmentNumber, string $segmentValue)
    {
        $segment = static::segmentValues()
                        ->forSegment($segmentNumber)
                        ->where('segment_value', $segmentValue)
                        ->where('is_active', true)
                        ->first();

        return $segment ? $segment->segment_description : null;
    }

    /**
     * Validate if segment value exists
     */
    public static function isValidSegmentValue(int $segmentNumber, string $segmentValue): bool
    {
        return static::segmentValues()
                    ->forSegment($segmentNumber)
                    ->where('segment_value', $segmentValue)
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Create structure definition
     */
    public static function createStructureDefinition(int $segmentNumber, string $description)
    {
        return static::create([
            'segment_type' => self::TYPE_STRUCTURE_DEFINITION,
            'segment_number' => $segmentNumber,
            'segment_value' => null,
            'segment_description' => $description,
            'is_active' => true
        ]);
    }

    /**
     * Create segment value
     */
    public static function createSegmentValue(int $segmentNumber, string $value, string $description)
    {
        return static::create([
            'segment_type' => self::TYPE_SEGMENT_VALUE,
            'segment_number' => $segmentNumber,
            'segment_value' => $value,
            'segment_description' => $description,
            'is_active' => true
        ]);
    }
}
