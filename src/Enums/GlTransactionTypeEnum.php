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

    public function colorClass(): string
    {
        return match($this) {
            self::MANUAL_GL => 'bg-blue-500',
            self::BANK => 'bg-green-500',
            self::RECEIVABLE => 'bg-yellow-500',
            self::PAYABLE => 'bg-red-500',
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
     * Get module code for fiscal period management
     * This matches the codes used in fiscal period open/close operations
     */
    public function moduleCode(): string
    {
        return match($this) {
            self::MANUAL_GL => 'GL',
            self::BANK => 'BNK',
            self::RECEIVABLE => 'RM',
            self::PAYABLE => 'PM',
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

    /**
     * Get enum from module code (GL, BNK, RM, PM)
     */
    public static function fromModuleCode(string $moduleCode): ?self
    {
        return match(strtoupper($moduleCode)) {
            'GL' => self::MANUAL_GL,
            'BNK' => self::BANK,
            'RM' => self::RECEIVABLE,
            'PM' => self::PAYABLE,
            default => null,
        };
    }

    /**
     * Get all valid module codes
     */
    public static function getValidModuleCodes(): array
    {
        return array_map(fn ($case) => $case->moduleCode(), self::cases());
    }

    /**
     * Get module code to enum mapping
     */
    public static function moduleCodeMapping(): array
    {
        return [
            'GL' => self::MANUAL_GL,
            'BNK' => self::BANK,
            'RM' => self::RECEIVABLE,
            'PM' => self::PAYABLE,
        ];
    }

}
