<?php

namespace Condoedge\Finance\Models;

enum PaymentInstallPeriodStatusEnum: int
{
    case PENDING = 1;
    case PAID = 2;
    case OVERDUE = 3;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('translate.pending'),
            self::PAID => __('translate.paid'),
            self::OVERDUE => __('translate.overdue'),
        };
    }

    public function class(): string
    {
        return match ($this) {
            self::PENDING => 'bg-warning',
            self::PAID => 'bg-positive',
            self::OVERDUE => 'bg-danger',
        };
    }

    public function pill()
    {
        return _Pill($this->label())
            ->class('text-sm font-semibold text-white')
            ->class($this->class());
    }

}
