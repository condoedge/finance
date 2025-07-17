<?php

namespace Condoedge\Finance\Models;

enum ExpenseReportTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;
    
    case GENERAL = 1;
    case TRAVEL = 2;
    case MEAL = 3;
    case OTHER = 4;

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => __('translate.general'),
            self::TRAVEL => __('translate.travel'),
            self::MEAL => __('translate.meal'),
            self::OTHER => __('translate.other'),
        };
    }
}