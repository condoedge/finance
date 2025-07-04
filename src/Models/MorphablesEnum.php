<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\CustomerPaymentModel;
use Condoedge\Finance\Models\GlobalScopesTypes\Credit;

// This is done to have a map for morhables using integer values for the enum
enum MorphablesEnum: int
{
    case PAYMENT = 1;
    case CREDIT = 2;

    public function getMorphableClass(): string
    {
        return match ($this) {
            self::PAYMENT => CustomerPaymentModel::getClass(),
            self::CREDIT => Credit::class,
        };
    }

    public static function getFromM($morphable): self
    {
        return match (true) {
            $morphable instanceof (CustomerPaymentModel::getClass()) => self::PAYMENT,
            $morphable instanceof Credit => self::CREDIT,
            default => throw new \InvalidArgumentException('Invalid morphable type')
        };
    }
}
