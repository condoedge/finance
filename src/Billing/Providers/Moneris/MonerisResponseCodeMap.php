<?php

namespace Condoedge\Finance\Billing\Providers\Moneris;

use Condoedge\Finance\Billing\Core\ErrorClassification;

/**
 * Moneris MPG response codes (see Moneris MPG developer guide).
 *   "00" .. "49" : approved
 *   "50" .. "99" : declined (customer issue — bad card, insufficient funds)
 *   "476", "477", "479" etc. : auth/setup issues
 *   null / non-numeric : system error (retry candidate)
 *
 * This map lives separately so the provider class stays focused on flow and the
 * codes are easy to revise without touching call sites.
 */
final class MonerisResponseCodeMap
{
    /**
     * True when Moneris considers the txn approved. Anything else is failure;
     * the failure category is decided by classify().
     */
    public static function isApproved(?string $code): bool
    {
        if ($code === null || $code === '' || !ctype_digit($code)) {
            return false;
        }

        $n = (int) $code;
        return $n <= 49;
    }

    /**
     * Classification for ErrorClassification — drives fallback decisions in
     * PaymentProcessor::attemptChain(). Customer-side failures (declines) are
     * PERMANENT and stop the chain; system / network errors are TRANSIENT.
     */
    public static function classify(?string $code, ?string $message = null): ErrorClassification
    {
        if ($code === null || $code === '') {
            return ErrorClassification::transient('moneris_system_error', $message);
        }

        if (!ctype_digit($code)) {
            // Non-numeric codes are Moneris-specific (e.g. "null", "Global Error Receipt").
            return ErrorClassification::transient('moneris_system_' . $code, $message);
        }

        $n = (int) $code;

        if ($n <= 49) {
            // Approved — shouldn't be calling classify().
            return ErrorClassification::unknown($message);
        }

        // 475-485 range is Moneris config / auth issues per their docs.
        if ($n >= 475 && $n <= 485) {
            return ErrorClassification::authFailure('moneris_' . $code, $message);
        }

        // Everything else 50-99 = customer-side decline.
        return ErrorClassification::permanent('moneris_' . $code, $message);
    }
}
