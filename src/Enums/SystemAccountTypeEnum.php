<?php

namespace Condoedge\Finance\Enums;

/**
 * System Account Type Enum
 *
 * Semantic designation for segment values that play a fixed structural role
 * in reporting (e.g. "the cash account", "the bank account"). This lets
 * reports resolve those accounts without relying on magic natural-account
 * code strings.
 *
 * Follows the same pattern as other enums in the finance package for
 * consistency and to enable facade usage.
 */
enum SystemAccountTypeEnum: string
{
    use \Kompo\Models\Traits\EnumKompo;

    case CASH = 'cash';
    case BANK = 'bank';

    /**
     * Get human-readable label for the system account type
     */
    public function label(): string
    {
        return match ($this) {
            self::CASH => __('finance.system-account-cash'),
            self::BANK => __('finance.system-account-bank'),
        };
    }
}
