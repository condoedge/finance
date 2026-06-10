<?php

namespace Condoedge\Finance\Models;

enum InvoiceStatusEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case DRAFT = 1;
    case PENDING = 2;
    case PAID = 3;
    case CANCELLED = 4;
    case OVERDUE = 5;
    case PARTIAL = 6;

    public function label(): string
    {
        return match ($this) {
            default => __($this->rawTranslationKey()),
        };
    }

    public function rawTranslationKey(): string
    {
        return match ($this) {
            self::DRAFT => 'finance-draft',
            self::PENDING => 'finance-pending',
            self::PAID => 'finance-paid',
            self::CANCELLED => 'finance-cancelled',
            self::OVERDUE => 'finance-overdue',
            self::PARTIAL => 'finance-partial',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::DRAFT => 'draft',
            self::PENDING => 'pending',
            self::PAID => 'paid',
            self::CANCELLED => 'cancelled',
            self::OVERDUE => 'overdue',
            self::PARTIAL => 'partial',
        };
    }

    public function class(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-gray-500',
            self::PENDING => 'bg-warning',
            self::PAID => 'bg-positive',
            self::CANCELLED => 'bg-danger',
            self::OVERDUE => 'bg-danger',
            self::PARTIAL => 'bg-warning',
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
        return match ($this) {
            self::PENDING, self::OVERDUE, self::PARTIAL => true,
            default => false,
        };
    }

    public static function allToBePaid(): array
    {
        return [
            self::OVERDUE,
            self::PENDING,
        ];
    }
}
