<?php

namespace Condoedge\Finance\Models;

enum PaymentInstallmentEnum: int
{
    use \Kompo\Auth\Models\Traits\EnumKompo;
    
    case ONE_TIME = 1;
    case TWO_TIMES = 2;
    case THREE_TIMES = 3;
    case FOUR_TIMES = 4;

    public function label()
    {
        return match($this) {
            self::ONE_TIME => __('translate.finance.one-time'),
            self::TWO_TIMES => __('translate.finance.two-times'),
            self::THREE_TIMES => __('translate.finance.three-times'),
            self::FOUR_TIMES => __('translate.finance.four-times'),
        };
    }
}