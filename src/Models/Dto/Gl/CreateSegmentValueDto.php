<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use WendellAdriel\ValidatedDTO\Attributes\Rules; // Not strictly needed if using methods, but good for consistency
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create Segment Value DTO
 * 
 * Used to create specific values for GL account segments.
 * Segment values are the actual codes used within each segment structure.
 * 
 * @property int $segment_number The segment number this value belongs to (references fin_gl_account_segment_structures.segment_number)
 * @property string $segment_value The actual code/value (e.g., "01", "PROJ", "SALES")
 * @property string $segment_description Human-readable description of this value
 * @property int $team_id The team/company this segment value belongs to
 * @property int $gl_account_segment_structure_id The ID of the segment structure this value belongs to
 */
class CreateSegmentValueDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $segment_number;
    public string $segment_value;
    public string $segment_description;
    public int $team_id;
    public int $gl_account_segment_structure_id; // Added this property

    /**
     * Defines the types for DTO properties.
     * // Defines the casts for the DTO properties.
     */
    protected function casts(): array
    {
        return [
            'segment_number' => new IntegerCast(),
            'segment_value' => new StringCast(),
            'segment_description' => new StringCast(),
            'team_id' => new IntegerCast(),
            'gl_account_segment_structure_id' => new IntegerCast(), // Added cast for the new property
        ];
    }

    /**
     * Defines the validation rules for the DTO properties.
     * // Validation rules
     */
    protected function rules(): array
    {
        return [
            'segment_number' => ['required', 'integer', 'min:1'], // Assuming segment_number is part of a composite key or for reference
            'segment_value' => ['required', 'string', 'max:50'], // Max length can be adjusted
            'segment_description' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'gl_account_segment_structure_id' => ['required', 'integer', 'exists:fin_gl_account_segment_structures,id'], // Added rule for the new property
            // Add unique rule for segment_value within the same gl_account_segment_structure_id and team_id if needed
            // Rule::unique('fin_gl_account_segment_values')->where(...)
        ];
    }

    // The static create method and constructor are replaced by ValidatedDTO's instantiation capabilities.
    // If specific default logic beyond EmptyDefaults is needed, the defaults() method can be used.
    /*
    protected function defaults(): array
    {
        return [];
    }
    */
}
