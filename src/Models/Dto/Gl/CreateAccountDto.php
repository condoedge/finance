<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Services\AccountSegmentValidator;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Create Account DTO
 *
 * Used to create new GL accounts from segment values.
 * Handles validation of segment value IDs and account attributes.
 *
 * @property array $segment_value_ids Array of segment value IDs to compose the account
 * @property string|null $account_description Description/name of the account
 * @property string|null $account_type Type from AccountTypeEnum (ASSET, LIABILITY, etc.)
 * @property bool $is_active Whether the account is active
 * @property bool $allow_manual_entry Whether manual GL entries are allowed
 * @property int|null $team_id Team ID (defaults to current team)
 * @property bool $apply_defaults Whether to apply default handlers for missing segments
 */
class CreateAccountDto extends ValidatedDTO
{
    public array $segment_value_ids;
    public ?string $account_description;
    public ?string $account_type;
    public bool $is_active;
    public bool $allow_manual_entry;
    public ?int $team_id;
    public ?bool $apply_defaults;

    public function rules(): array
    {
        return [
            'segment_value_ids' => 'required|array|min:1',
            'segment_value_ids.*' => 'nullable|integer|exists:fin_segment_values,id',
            'account_description' => 'nullable|string|max:255',
            'account_type' => 'nullable|string',
            'is_active' => 'required|boolean',
            'allow_manual_entry' => 'required|boolean',
            'team_id' => 'nullable|integer',
            'apply_defaults' => 'nullable|boolean',
        ];
    }

    public function casts(): array
    {
        return [
            'segment_value_ids' => new ArrayCast(),
            'account_description' => new StringCast(),
            'account_type' => new StringCast(),
            'is_active' => new BooleanCast(),
            'allow_manual_entry' => new BooleanCast(),
            'apply_defaults' => new BooleanCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'account_description' => null,
            'account_type' => null,
            'is_active' => true,
            'allow_manual_entry' => true,
            'team_id' => null,
            'apply_defaults' => false,
        ];
    }

    public function after(\Illuminate\Validation\Validator $validator): void
    {
        $segmentValueIds = $this->dtoData['segment_value_ids'] ?? [];
        $applyDefaults = $this->dtoData['apply_defaults'] ?? false;

        // Skip completeness validation if applying defaults
        // as defaults will fill in missing segments
        if (!$applyDefaults) {
            // Validate segment values exist and are active
            $segmentsValidator = app(AccountSegmentValidator::class);
            $segmentsValidator->validateCompleteness($segmentValueIds);
            $segmentsValidator->validateUniqueness($segmentValueIds);
            $segmentsValidator->validateCompatibility($segmentValueIds);
            $segmentsValidator->validateAreActive($segmentValueIds);
        }
    }
}
