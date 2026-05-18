<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Models\Rebate;

enum RebateAmountTypeEnum: int
{
    case PERCENT = 1;
    case AMOUNT = 2;

    public function getAmountSymbol(): string
    {
        return match ($this) {
            self::PERCENT => '%',
            self::AMOUNT => '$',
        };
    }

    public function getVisualAmount(Rebate $rebate): string
    {
        return match ($this) {
            self::PERCENT => $rebate->amount . '%',
            self::AMOUNT => currency($rebate->amount),
        };
    }

    public function getAmountOff(Rebate $rebate, $amount)
    {
        return match ($this) {
            self::PERCENT => $amount * ($rebate->amount / 100),
            self::AMOUNT => min($amount, $rebate->amount),
        };
    }
}
