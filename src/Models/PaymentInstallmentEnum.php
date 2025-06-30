<?php

namespace Condoedge\Finance\Models;

enum PaymentInstallmentEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case ONE_TIME = 1;

    /**
     * Get human-readable label for the payment type
     */
    public function label(): string
    {
        return match($this) {
            default => __('translate.one-time'),
        };
    }

    /**
     * Get short code for the payment type
     */
    public function code(): string
    {
        return match($this) {
            default => 'OT',
        };
    }

    public function getTimes(): int
    {
        return match($this) {
            self::ONE_TIME => 1,
        };
    }

    public function getNextDate($from = null): \Illuminate\Support\Carbon
    {
        $date = $from ?: now();
        return $date->addDays(30);
    }
}
