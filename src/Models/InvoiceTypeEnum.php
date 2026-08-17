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
            default => __($this->rawTranslationKey()),
        };
    }

    public function rawTranslationKey(): string
    {
        return match ($this) {
            self::INVOICE => 'finance-invoice',
            // "Credit" on its own reads as a credit card to a treasurer.
            self::CREDIT => 'finance-credit-note',
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
