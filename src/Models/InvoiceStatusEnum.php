<?php

namespace Condoedge\Finance\Models;

enum InvoiceStatusEnum: int
{
    use \Kompo\Auth\Models\Traits\EnumKompo;
    
    case DRAFT = 1;
    case APPROVED = 2;
    case SENT = 3;
    case PARTIALLY_PAID = 4;
    case PAID = 5;
    case VOIDED = 10;

    public function label()
    {
        return match($this) {
            self::DRAFT => __('finance.draft'),
            self::APPROVED => __('finance.approved'),
            self::SENT => __('finance.sent'),
            self::PARTIALLY_PAID => __('finance.partial'),
            self::PAID => __('finance.paid'),
            self::VOIDED => __('finance.void'),
        };
    }

    public function classes()
    {
        return match($this) {
            self::DRAFT => 'bg-graylight text-graydark',
            self::APPROVED => 'bg-infolight text-info',
            self::SENT => 'bg-graylight bg-graydark',
            self::PARTIALLY_PAID => 'bg-warninglight text-warningdark',
            self::PAID => 'bg-greenlight text-greendark',
            self::VOIDED => 'bg-dangerlight text-dangerdark',
        };
    }
}