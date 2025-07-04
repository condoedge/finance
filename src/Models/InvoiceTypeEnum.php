<?php

namespace Condoedge\Finance\Models;

enum InvoiceTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case INVOICE = 1;
    case CREDIT = 2;

    public function label(): string
    {
        return match ($this) {
            self::INVOICE => __('finance-invoice'),
            self::CREDIT => __('finance-credit'),
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::INVOICE => 'INV',
            self::CREDIT => 'CRD',
        };
    }

    public function signMultiplier(): int
    {
        return match ($this) {
            self::INVOICE => 1,
            self::CREDIT => -1,
        };
    }
}
