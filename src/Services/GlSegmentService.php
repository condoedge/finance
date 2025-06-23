<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Models\GlAccountSegment;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentStructureDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;

/**
 * Service for managing GL Account Segments
 * 
 * This service abstracts the segment management logic from models
 * and provides an easy-to-override interface for external packages
 */
class GlSegmentService
{
    /**
     * Create segment structure definition
     * 
     * Example: Define that segment 1 is "Project", segment 2 is "Activity", etc.
     */
    public function createSegmentStructure(CreateSegmentStructureDto $dto): GlAccountSegment
    {
        return GlAccountSegment::create([
            'segment_type' => GlAccountSegment::TYPE_STRUCTURE,
            'segment_number' => $dto->segment_number,
            'segment_value' => null, // Structure definitions have no value
            'segment_description' => $dto->segment_description,
            'team_id' => $dto->team_id,
        ]);
    }
    
    /**
     * Create segment value
     * 
     * Example: For segment 1 (Project), create value "04" with description "project04"
     */
    public function createSegmentValue(CreateSegmentValueDto $dto): GlAccountSegment
    {
        // Validate that the segment structure exists
        $this->validateSegmentStructureExists($dto->segment_number, $dto->team_id);
        
        $segment = new GlAccountSegment();
        $segment->segment_position = $dto->segment_number;
        $segment->segment_value = $dto->segment_value;
        $segment->segment_description = $dto->segment_description;
        $segment->team_id = $dto->team_id;

        return $segment->save();
    }
    
    /**
     * Setup default segment structure for a team
     * 
     * Creates a 3-segment structure: Project - Activity - Account
     */
    public function setupDefaultSegmentStructure(int $teamId): void
    {
        $structures = [
            CreateSegmentStructureDto::create(1, 'Project', $teamId),
            CreateSegmentStructureDto::create(2, 'Activity', $teamId),
            CreateSegmentStructureDto::create(3, 'Natural Account', $teamId),
        ];
        
        foreach ($structures as $structure) {
            // Only create if it doesn't exist
            if (!$this->segmentStructureExists($structure->segment_number, $teamId)) {
                $this->createSegmentStructure($structure);
            }
        }
    }
    
    /**
     * Setup sample segment values for demonstration
     * 
     * This creates the example values from your requirements:
     * - Segment 1: "04" = "project04"
     * - Segment 2: "205" = "Construction", "405" = "Operation"
     * - Segment 3: "1105" = "Material expense", "4105" = "Fuel expense"
     */
    public function setupSampleSegmentValues(int $teamId): void
    {
        $values = [
            // Segment 1 values (Projects)
            CreateSegmentValueDto::create(1, '04', 'project04', $teamId),
            CreateSegmentValueDto::create(1, '05', 'project05', $teamId),
            
            // Segment 2 values (Activities)
            CreateSegmentValueDto::create(2, '205', 'Construction', $teamId),
            CreateSegmentValueDto::create(2, '405', 'Operation', $teamId),
            
            // Segment 3 values (Natural Accounts)
            CreateSegmentValueDto::create(3, '1105', 'Material expense', $teamId),
            CreateSegmentValueDto::create(3, '4105', 'Fuel expense', $teamId),
        ];
        
        foreach ($values as $value) {
            // Only create if it doesn't exist
            if (!$this->segmentValueExists($value->segment_number, $value->segment_value, $teamId)) {
                $this->createSegmentValue($value);
            }
        }
    }
    
    /**
     * Validate that a complete account ID is valid
     * 
     * Example: "04-205-1105" checks that:
     * - "04" exists in segment 1
     * - "205" exists in segment 2  
     * - "1105" exists in segment 3
     */
    public function validateAccountId(string $accountId, int $teamId): bool
    {
        $segments = GlAccountSegment::parseAccountId($accountId);
        
        foreach ($segments as $position => $value) {
            $segmentNumber = $position + 1; // Convert 0-based to 1-based
            
            if (!GlAccountSegment::isValidSegmentValue($segmentNumber, $value, $teamId)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get account description from segment values
     * 
     * Example: "04-205-1105" returns "project04 - Construction - Material expense"
     */
    public function getAccountDescription(string $accountId, int $teamId): string
    {
        $segments = GlAccountSegment::parseAccountId($accountId);
        $descriptions = [];
        
        foreach ($segments as $position => $value) {
            $segmentNumber = $position + 1; // Convert 0-based to 1-based
            $description = GlAccountSegment::getSegmentDescription($segmentNumber, $value, $teamId);
            $descriptions[] = $description ?: $value;
        }
        
        return implode(' - ', $descriptions);
    }
    
    /**
     * Get all segment structures for a team
     */
    public function getSegmentStructures(int $teamId): \Illuminate\Support\Collection
    {
        return GlAccountSegment::getSegmentStructureForTeam($teamId);
    }
    
    /**
     * Get all values for a specific segment
     */
    public function getSegmentValues(int $segmentNumber, int $teamId): \Illuminate\Support\Collection
    {
        return GlAccountSegment::getSegmentValuesForNumber($segmentNumber, $teamId);
    }
    
    /**
     * Check if segment structure exists
     */
    protected function segmentStructureExists(int $segmentNumber, int $teamId): bool
    {
        return GlAccountSegment::forTeam($teamId)
            ->structureDefinitions()
            ->forSegmentNumber($segmentNumber)
            ->exists();
    }
    
    /**
     * Check if segment value exists
     */
    protected function segmentValueExists(int $segmentNumber, string $segmentValue, int $teamId): bool
    {
        return GlAccountSegment::isValidSegmentValue($segmentNumber, $segmentValue, $teamId);
    }
    
    /**
     * Validate that segment structure exists before creating values
     */
    protected function validateSegmentStructureExists(int $segmentNumber, int $teamId): void
    {
        if (!$this->segmentStructureExists($segmentNumber, $teamId)) {
            throw new \InvalidArgumentException("Segment structure for position {$segmentNumber} does not exist for team {$teamId}");
        }
    }
}
