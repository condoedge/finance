<?php

namespace Condoedge\Finance\Models;

use Condoedge\Finance\Billing\Kompo\PaymentCreditCardForm;
use Faker\Provider\ar_EG\Payment;

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
            self::CASH => __('finance-cash'),
            self::CHECK => __('finance-check'),
            self::CREDIT_CARD => __('finance-credit-card'),
            self::BANK_TRANSFER => __('finance-bank-transfer'),
            self::INTERAC => __('translate.finance-interac'),
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
            self::INTERAC => 'INTERAC',
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

    public function getDefaultPaymentGateway()
    {
        return config('kompo-finance.payment_method_providers')[$this->value] ?? null;
    }

    /**
     * Get the account for this payment gateway
     */
    public function getReceivableAccount(): ?GlAccount
    {
        return match ($this) {
            default => GlAccount::getFromLatestSegmentValue(SegmentValue::first()?->id), // TODO WE MUST SET A CORRECT VALUE HERE
        };
    }

    public function online()
    {
        return match ($this) {
            self::CREDIT_CARD, self::INTERAC => true,
            default => false,
        };
    }

    public function form($invoice)
    {
        return match ($this) {
            self::CREDIT_CARD => new PaymentCreditCardForm($invoice->id),
            default => null,
        };
    }
}
