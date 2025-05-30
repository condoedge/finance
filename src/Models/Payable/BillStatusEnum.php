<?php

namespace Condoedge\Finance\Models\Payable;

enum BillStatusEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case DRAFT = 1;
    case PENDING = 2;
    case PAID = 3;
    case CANCELLED = 4;

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('translate.draft'),
            self::PENDING => __('translate.pending'),
            self::PAID => __('translate.paid'),
            self::CANCELLED => __('translate.cancelled'),
        };
    }

    public function class(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-graylight',
            self::PENDING => 'bg-warning',
            self::PAID => 'bg-positive',
            self::CANCELLED => 'bg-danger',
        };
    }

    public function pill()
    {
        return _Pill($this->label())
            ->class('text-sm font-semibold text-white')
            ->class($this->class());
    }

    public function canBePaid(): bool
    {
        return $this === self::PENDING;
    }
}
