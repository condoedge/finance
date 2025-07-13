<?php

namespace Condoedge\Finance\Models;

enum PaymentTraceStatusEnum: int 
{
    use \Kompo\Models\Traits\EnumKompo;

    case INITIATED = 1;
    case PROCESSING = 2;
    case COMPLETED = 3;
    case FAILED = 4;

    public function label(): string
    {
        return match($this) {
            self::INITIATED => __('translate.finance-payment-initiated'),
            self::PROCESSING => __('translate.finance-payment-processing'),
            self::COMPLETED => __('translate.finance-payment-completed'),
            self::FAILED => __('translate.finance-payment-failed'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::INITIATED => 'bg-info',
            self::PROCESSING => 'bg-warning',
            self::COMPLETED => 'bg-positive',
            self::FAILED => 'bg-danger',
        };
    }

    public function pill()
    {
        return _Pill($this->label())->class($this->color())->class('text-white');
    }
}