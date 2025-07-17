<?php

namespace Condoedge\Finance\Models;

enum ExpenseReportStatusEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case PENDING = 1;
    case APPROVED = 2;
    case REJECTED = 3;
    case PAID = 4;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('finance-pending'),
            self::APPROVED => __('finance-approved'),
            self::REJECTED => __('finance-rejected'),
            self::PAID => __('finance-paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'bg-warning',
            self::APPROVED => 'bg-positive',
            self::REJECTED => 'bg-danger',
            self::PAID => 'bg-info',
        };
    }

    public function pill()
    {
        return _Pill($this->label())->class('text-white')->class($this->color());
    }
}
