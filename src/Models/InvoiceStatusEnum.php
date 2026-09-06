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

    public function label($i = null): string
    {
        return match ($this) {
            default => __($this->rawTranslationKey($i)),
        };
    }

    public function rawTranslationKey($i = null): string
    {
        return match ($this) {
            self::DRAFT => 'finance-draft',
            self::PENDING => 'finance-pending',
            self::PAID => $i?->invoice_type_id != InvoiceTypeEnum::CREDIT ? 'finance-paid' : 'finance-used',
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

    public function pill($i = null)
    {
        return _Pill($this->label($i))
            ->class('text-sm font-semibold text-white')
            ->class($this->class());
    }

    public function canBePaid(): bool
    {
        return in_array($this, self::allCanBePaid(), true);
    }

    /**
     * Every status with a balance still open. One list, so the buttons and the queries
     * behind them cannot drift apart.
     *
     * @return array<int, self>
     */
    public static function allCanBePaid(): array
    {
        return [self::PENDING, self::OVERDUE, self::PARTIAL];
    }

    public static function allToBePaid(): array
    {
        return [
            self::OVERDUE,
            self::PENDING,
        ];
    }
}
