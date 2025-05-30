<?php

namespace Condoedge\Finance\Models\Payable;

enum BillTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case BILL = 1;
    case CREDIT = 2;

    public function label(): string
    {
        return match ($this) {
            self::BILL => __('translate.bill'),
            self::CREDIT => __('translate.credit'),
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::BILL => 'BILL',
            self::CREDIT => 'BCRD',
        };
    }

    public function signMultiplier(): int
    {
        return match ($this) {
            self::BILL => 1,
            self::CREDIT => -1,
        };
    }
}
