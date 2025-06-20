<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Billing\TempPaymentGateway;
use Condoedge\Finance\Services\PaymentGatewayService;

/**
 * Payment Type Enum
 * 
 * Defines the available payment types in the system.
 * This enum is linked to the fin_payment_types table for referential integrity.
 */
enum PaymentTypeEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;
    
    case CASH = 1;
    case CHECK = 2;
    case CREDIT_CARD = 3;
    case BANK_TRANSFER = 4;
    case CREDIT_NOTE = 5;

    /**
     * Get human-readable label for the payment type
     */
    public function label(): string
    {
        return match($this) {
            self::CASH => 'Cash',
            self::CHECK => 'Check',
            self::CREDIT_CARD => 'Credit Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CREDIT_NOTE => 'Credit Note',
        };
    }

    /**
     * Get short code for the payment type
     */
    public function code(): string
    {
        return match($this) {
            self::CASH => 'CASH',
            self::CHECK => 'CHECK',
            self::CREDIT_CARD => 'CC',
            self::BANK_TRANSFER => 'WIRE',
            self::CREDIT_NOTE => 'CN',
        };
    }

    /**
     * Check if this payment type requires bank account information
     */
    public function requiresBankAccount(): bool
    {
        return match($this) {
            self::CHECK, self::BANK_TRANSFER => true,
            default => false,
        };
    }

    /**
     * Check if this payment type can have negative amounts
     */
    public function allowsNegativeAmounts(): bool
    {
        return $this === self::CREDIT_NOTE;
    }

    /**
     * Get all payment types as array for validation
     */
    public static function getValidValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if this payment type can have negative amounts
     */
    public function allowsNegativeAmounts(): bool
    {
        return $this === self::CREDIT_NOTE;
    }

    /**
     * Get all payment types as array for validation
     */
    public static function getValidValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getPaymentGateway()
    {
        return match ($this) {
            default => TempPaymentGateway::class,
        };
    }
}
