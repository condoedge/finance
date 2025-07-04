<?php

namespace Condoedge\Finance\Models\Dto\Gl;

/**
 * Create Segment Structure DTO
 *
 * Used to create new GL account segment structures.
 * Segments define the structure of account codes in the general ledger.
 *
 * @property int $segment_number The position/order of this segment in the account structure
 * @property string $segment_description Human-readable description of what this segment represents
 * @property int $team_id The team/company this segment structure belongs to
 */
class CreateSegmentStructureDto
{
    public function __construct(
        public int $segment_number,
        public string $segment_description,
        public int $team_id,
    ) {
    }

    /**
     * Create a new instance with the provided parameters
     *
     * @param int $segmentNumber The position/order of this segment
     * @param string $description Human-readable description
     * @param int $teamId The team this segment belongs to
     *
     * @return self
     */
    public static function create(
        int $segmentNumber,
        string $description,
        int $teamId
    ): self {
        return new self(
            segment_number: $segmentNumber,
            segment_description: $description,
            team_id: $teamId
        );
    }
}
