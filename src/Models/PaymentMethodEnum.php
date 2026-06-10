<?php

namespace Condoedge\Finance\Models;

/**
 * Payment Type Enum
 *
 * Defines the available payment types in the system.
 * This enum is linked to the fin_payment_methods table for referential integrity.
 */
enum PaymentMethodEnum: int
{
    use \Kompo\Models\Traits\EnumKompo;

    case CASH = 1;
    case CHECK = 2;
    case CREDIT_CARD = 3;
    case BANK_TRANSFER = 4;
    case INTERAC = 5;

    /**
     * Get human-readable label for the payment type
     */
    public function label(): string
    {
        return match($this) {
            default => __($this->rawTranslationKey()),
        };
    }

    public function rawTranslationKey(): string
    {
        return match($this) {
            self::CREDIT_CARD => 'finance-credit-card',
            self::BANK_TRANSFER => 'finance-bank-transfer',
            self::INTERAC => 'finance-interac',
            self::CASH => 'finance-cash',
            self::CHECK => 'finance-check',
        };
    }

    /**
     * Get short code for the payment type
     */
    public function code(): string
    {
        return match($this) {
            self::CREDIT_CARD => 'CC',
            self::BANK_TRANSFER => 'WIRE',
            self::INTERAC => 'INTERAC',
            self::CASH => 'CASH',
            self::CHECK => 'CHECK',
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
     * Get all payment types as array for validation
     */
    public static function getValidValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @deprecated Use PaymentGatewayResolverInterface::resolveChain($context) instead.
     * The dynamic provider system (fin_team_payment_providers) replaces this static
     * map. Kept for backwards compatibility with callers that don't yet pass a
     * PaymentContext (resolver still consults this as a fallback when no row
     * exists for the team).
     */
    public function getDefaultPaymentGateway()
    {
        return config('kompo-finance.payment_method_providers')[$this->value] ?? null;
    }

    public function online()
    {
        return match ($this) {
            self::CREDIT_CARD, self::INTERAC, self::BANK_TRANSFER => true,
            default => false,
        };
    }
}
