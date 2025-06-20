<?php

namespace Condoedge\Finance\Enums;

/**
 * GL Transaction Type Enum
 * 
 * Defines the types of General Ledger transactions that can be created.
 * Each type has specific rules for posting periods and validation.
 * 
 * CRITICAL: This enum follows the same pattern as other financial enums in the system
 * for consistency and to enable facade usage.
 */
enum GlTransactionTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case MANUAL_GL = 1;
    case BANK = 2;
    case RECEIVABLE = 3;
    case PAYABLE = 4;

    /**
     * Get human-readable label for the transaction type
     */
    public function label(): string
    {
        return match($this) {
            self::MANUAL_GL => 'Manual GL',
            self::BANK => 'Bank Transaction',
            self::RECEIVABLE => 'Accounts Receivable',
            self::PAYABLE => 'Accounts Payable',
        };
    }

    /**
     * Get the fiscal period field name for checking if open
     */
    public function getFiscalPeriodOpenField(): string
    {
        return match($this) {
            self::MANUAL_GL => 'is_open_gl',
            self::BANK => 'is_open_bnk', 
            self::RECEIVABLE => 'is_open_rm',
            self::PAYABLE => 'is_open_pm',
        };
    }

    /**
     * Get short code for transaction ID generation
     */
    public function code(): string
    {
        return match($this) {
            self::MANUAL_GL => 'GL',
            self::BANK => 'BNK',
            self::RECEIVABLE => 'AR',
            self::PAYABLE => 'AP',
        };
    }

    /**
     * Check if this transaction type allows manual account entries
     */
    public function allowsManualAccountEntry(): bool
    {
        return $this === self::MANUAL_GL;
    }

    /**
     * Get all transaction types as array for validation
     */
    public static function getValidValues(): array
    {
        return array_column(self::cases(), 'value');
    }

}
