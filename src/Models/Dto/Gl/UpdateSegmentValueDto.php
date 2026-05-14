<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Enums\SystemAccountTypeEnum;
use Condoedge\Finance\Models\AccountTypeEnum;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\EnumCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Concerns\EmptyDefaults;
use WendellAdriel\ValidatedDTO\ValidatedDTO;

/**
 * Update Segment Value DTO

 *
 * @property int $id
 * @property string $segment_description Human-readable description
 * @property AccountTypeEnum $account_type Type of account this segment value belongs to
 * @property SystemAccountTypeEnum $system_account_type Optional system role (cash/bank) of this segment value
 */
class UpdateSegmentValueDto extends ValidatedDTO
{
    use EmptyDefaults;

    public int $id;
    public string $segment_description;
    public ?AccountTypeEnum $account_type;
    public ?SystemAccountTypeEnum $system_account_type;

    public bool $allow_manual_entry;


    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:fin_segment_values,id',
            'segment_description' => 'required|string|max:255',
            'account_type' => 'nullable|in:' . collect(AccountTypeEnum::cases())->pluck('value')->implode(','),
            'system_account_type' => 'nullable|in:' . collect(SystemAccountTypeEnum::cases())->pluck('value')->implode(','),
            'allow_manual_entry' => 'boolean',
        ];
    }

    public function casts(): array
    {
        return [
            'id' => new IntegerCast(),
            'segment_description' => new StringCast(),
            'account_type' => new EnumCast(AccountTypeEnum::class),
            'system_account_type' => new EnumCast(SystemAccountTypeEnum::class),
            'allow_manual_entry' => new BooleanCast(),
        ];
    }

    public function defaults(): array
    {
        return [
            'allow_manual_entry' => true,
        ];
    }
}
