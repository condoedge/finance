<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\GlAccount;

/**
 * Account Segment Validator Service
 * 
 * Centralizes all segment validation logic to ensure consistency
 * across the application.
 */
class AccountSegmentValidator
{
    /**
     * Validate that all required segments have values
     * 
     * @param array $segmentValueIds Array of segment value IDs
     * @return void
     * @throws \InvalidArgumentException If validation fails
     */
    public function validateCompleteness(array $segmentValueIds): void
    {
        $requiredSegments = AccountSegment::get();
        
        $providedSegments = SegmentValue::whereIn('id', $segmentValueIds)
            ->with('segmentDefinition')
            ->get()
            ->keyBy('segmentDefinition.segment_position');
        
        foreach ($requiredSegments as $segment) {
            if (!isset($providedSegments[$segment->segment_position])) {
                throw new \InvalidArgumentException(
                    __('translate.with-values-missing-value-value-for-segment-position', [
                        'segment_position' => $segment->segment_position,
                        'segment_description' => $segment->segment_description
                    ])
                );
            }
        }
    }

    public function validateAreActive(array $segmentValueIds): void
    {
        $inactiveValues = SegmentValue::whereIn('id', $segmentValueIds)
            ->where('is_active', false)
            ->get();
        
        if ($inactiveValues->isNotEmpty()) {
            throw new \InvalidArgumentException(
                __('translate.with-values-inactive-value-value-for-segment-position', [
                    'segment_position' => $inactiveValues->first()->segmentDefinition->segment_position,
                    'segment_description' => $inactiveValues->first()->segmentDefinition->segment_description
                ])
            );
        }
    }
    
    /**
     * Validate that segment values are compatible
     * 
     * @param array $segmentValueIds
     * @return void
     * @throws \InvalidArgumentException If values are incompatible
     */
    public function validateCompatibility(array $segmentValueIds): void
    {
        // Add business rules for segment compatibility here
        // For example: certain account types can only use specific segment combinations
    }
    
    /**
     * Validate that account doesn't already exist
     * 
     * @param array $segmentValueIds
     * @param int|null $excludeAccountId For updates
     * @return void
     * @throws \InvalidArgumentException If account exists
     */
    public function validateUniqueness(array $segmentValueIds, ?int $excludeAccountId = null): void
    {
        $query = GlAccount::query();
        
        if ($excludeAccountId) {
            $query->where('id', '!=', $excludeAccountId);
        }
        
        foreach ($segmentValueIds as $segmentValueId) {
            $query->whereHas('segmentAssignments', function ($q) use ($segmentValueId) {
                $q->where('segment_value_id', $segmentValueId);
            });
        }
        
        if ($query->exists()) {
            throw new \InvalidArgumentException(__('translate.account-already-exists'));
        }
    }
    
    /**
     * Validate segment value length matches definition
     * 
     * @param int $segmentDefinitionId
     * @param string $value
     * @return void
     * @throws \InvalidArgumentException If length is invalid
     */
    public function validateSegmentValueLength(int $segmentDefinitionId, string $value): void
    {
        $definition = AccountSegment::findOrFail($segmentDefinitionId);
        
        if (strlen($value) != $definition->segment_length) {
            throw new \InvalidArgumentException(
                __('translate.value-length-mismatch', [
                    'value' => $value,
                    'length' => $definition->segment_length
                ])
            );
        }
    }
    
    /**
     * Validate segment position is not already taken
     * 
     * @param int $position
     * @param int|null $excludeId
     * @return void
     * @throws \InvalidArgumentException If position is taken
     */
    public function validateSegmentPosition(int $position, ?int $excludeId = null): void
    {
        $query = AccountSegment::where('segment_position', $position);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        if ($query->exists()) {
            throw new \InvalidArgumentException(__('translate.segment-position-taken', [
                'position' => $position
            ]));
        }
    }
}
