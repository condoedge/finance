<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\AccountTypeEnum;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Services\AccountSegmentValidator;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\EnumCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create Segment Value DTO
 * 
 * Used to create segment values that can be assigned to accounts.
 * Each segment value belongs to a specific segment definition (position).
 * 
 * @property int $segment_definition_id The segment definition this value belongs to
 * @property string $segment_value The actual value (e.g., '10', '03', '4000')
 * @property string $segment_description Human-readable description
 * @property bool $is_active Whether this value is active for use
 */
class CreateSegmentValueDto extends ValidatedDTO
{
    public int $segment_definition_id;
    public string $segment_value;
    public string $segment_description;
    public ?bool $is_active;
    public ?bool $allow_manual_entry;
    public ?AccountTypeEnum $account_type;

    public $segmentDefinition;
    
    public function rules(): array
    {
        return [
            'segment_definition_id' => 'required|integer|exists:fin_account_segments,id',
            'segment_value' => 'required|string|max:10',
            'segment_description' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
            'account_type' => 'nullable|in:' . collect(AccountTypeEnum::cases())->pluck('value')->implode(','),
        ];
    }
    
    public function casts(): array
    {
        return [
            'segment_definition_id' => new IntegerCast(),
            'segment_value' => new StringCast(),
            'segment_description' => new StringCast(),
            'is_active' => new BooleanCast(),
            'account_type' => new EnumCast(AccountTypeEnum::class),
        ];
    }
    
    public function defaults(): array
    {
        return [
            'is_active' => true,
            'allow_manual_entry' => true
        ];
    }

    public function after(\Illuminate\Validation\Validator $validator): void
    {
        $segmentDefinitionId  = $this->dtoData['segment_definition_id'] ?? null;
        $segmentValue = $this->dtoData['segment_value'] ?? null;

        $segmentsValidator = app(AccountSegmentValidator::class);

        // Validate value length
        try{
            $segmentsValidator->validateSegmentValueLength($segmentDefinitionId, $segmentValue);
        } catch (\InvalidArgumentException $e) {
            $validator->errors()->add('segment_value', $e->getMessage());
            return;
        }

        if ($segmentDefinitionId) {
            $this->segmentDefinition = AccountSegment::find($segmentDefinitionId);    
        }

        // Ensure segment value is unique within its segment definition
        $existing = SegmentValue::where('segment_definition_id', $segmentDefinitionId)
            ->where('segment_value', $segmentValue)
            ->first();

        if ($existing) {
            $validator->errors()->add('segment_value', __('translate.segment-value-already-exists'));
        }
    }
}
