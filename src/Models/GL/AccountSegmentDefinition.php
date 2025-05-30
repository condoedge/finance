<?php

namespace Condoedge\Finance\Models\GL;

use Condoedge\Finance\Models\AbstractMainFinanceModel;

class AccountSegmentDefinition extends AbstractMainFinanceModel
{
    protected $table = 'fin_account_segment_definitions';

    protected $fillable = [
        'segment_position',
        'segment_length',
        'segment_name',
        'segment_description',
        'is_active'
    ];

    protected $casts = [
        'segment_position' => 'integer',
        'segment_length' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Get active segment definitions ordered by position
     */
    public static function getActiveDefinitions()
    {
        return static::where('is_active', true)
                    ->orderBy('segment_position')
                    ->get();
    }

    /**
     * Get segment definition by position
     */
    public static function getByPosition(int $position)
    {
        return static::where('segment_position', $position)
                    ->where('is_active', true)
                    ->first();
    }

    /**
     * Validate account structure against defined segments
     */
    public static function validateAccountStructure(string $accountId): array
    {
        $segments = explode('-', $accountId);
        $definitions = static::getActiveDefinitions();
        $errors = [];

        if (count($segments) !== $definitions->count()) {
            $errors[] = "Account structure should have {$definitions->count()} segments, got " . count($segments);
            return $errors;
        }

        foreach ($definitions as $index => $definition) {
            $segment = $segments[$index] ?? '';
            
            if (strlen($segment) !== $definition->segment_length) {
                $errors[] = "Segment " . ($index + 1) . " ({$definition->segment_name}) should be {$definition->segment_length} characters, got " . strlen($segment);
            }
        }

        return $errors;
    }

    /**
     * Generate account format pattern
     */
    public static function getAccountFormatPattern(): string
    {
        $definitions = static::getActiveDefinitions();
        $pattern = [];
        
        foreach ($definitions as $definition) {
            $pattern[] = str_repeat('X', $definition->segment_length);
        }
        
        return implode('-', $pattern);
    }
}
