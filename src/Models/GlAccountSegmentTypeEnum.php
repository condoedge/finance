<?php

namespace Condoedge\Finance\Models;

enum GlAccountSegmentTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case STRUCTURE_DEFINITION = 1;
    case ACCOUNT_SEGMENT_VALUE = 2;

    public function label(): string
    {
        return match ($this) {
            self::STRUCTURE_DEFINITION => __('translate.structure_definition'),
            self::ACCOUNT_SEGMENT_VALUE => __('translate.account_segment_value'),
        };
    }
}
