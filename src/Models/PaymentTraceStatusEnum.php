<?php

namespace Condoedge\Finance\Models;

enum PaymentTraceStatusEnum: string 
{
    use \Kompo\Models\Traits\EnumKompo;

    case INITIATED = 'initiated';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::INITIATED => __('translate.finance-payment-initiated'),
            self::PROCESSING => __('translate.finance-payment-processing'),
            self::COMPLETED => __('translate.finance-payment-completed'),
            self::FAILED => __('translate.finance-payment-failed'),
        };
    }
}