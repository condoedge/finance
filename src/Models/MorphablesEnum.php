<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Facades\CustomerPaymentModel;
use Condoedge\Finance\Models\GlobalScopesTypes\Credit;
use Condoedge\Finance\Models\CustomerPayment;

// This is done to have a map for morhables using integer values for the enum
enum MorphablesEnum: int
{
    case PAYMENT = 1;
    case CREDIT = 2;

    public function getMorphableClass(): string
    {
        return match ($this) {
            self::PAYMENT => CustomerPayment::class,
            self::CREDIT => Credit::class,
        };
    }

    public static function getFromM($morphable): self
    {
        return match (true) {
            $morphable instanceof CustomerPayment => self::PAYMENT,
            $morphable instanceof Credit => self::CREDIT,
            default => throw new \InvalidArgumentException('Invalid morphable type')
        };
    }
}
