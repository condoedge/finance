<?php

namespace Condoedge\Finance\Models;

enum InvoiceTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case INVOICE = 1;

    public function label(): string
    {
        return match ($this) {
            self::INVOICE => __('translate.invoice'),
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::INVOICE => 'INV',
        };
    }

    
}