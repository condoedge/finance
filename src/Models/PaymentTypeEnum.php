<?php

namespace Condoedge\Finance\Models;

enum PaymentTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case CASH = 1;

    public function label(): string
    {
        return match ($this) {
            self::CASH => __('finance-cash'),
        };
    }

    public function getPaymentGateway()
    {
        return config('kompo-finance.payment_gateways')[$this->value] ?? null;
    }
}