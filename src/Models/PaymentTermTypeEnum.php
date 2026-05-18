<?php

namespace Condoedge\Finance\Models;

enum PaymentTermTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case COD = 1; // Cash on Delivery
    case NET = 2; // Net terms (e.g., Net 30, Net 60)
    case INSTALLMENT = 3; // Installment payments

    public function label(): string
    {
        return match ($this) {
            self::COD => __('finance-cod'),
            self::NET => __('finance-net'),
            self::INSTALLMENT => __('finance-installment'),
        };
    }

    public function settingsRules()
    {
        return match ($this) {
            self::COD => [],
            self::NET => [
                'days' => 'required|integer|min:1',
            ],
            self::INSTALLMENT => [
                'periods' => 'required|integer|min:1',
                'interval' => 'required|integer|min:1',
                'interval_type' => 'required|in:days,months,years',
            ],
        };
    }
}
