<?php

namespace Condoedge\Finance\Billing\Core;

/**
 * Outcome record passed to ProviderHealthCheckerInterface::record() after each
 * payment attempt. Encapsulates what just happened so the checker can update
 * the snapshot without re-fetching context.
 */
final class PaymentOutcome
{
    public function __construct(
        public readonly bool $success,
        public readonly ?ErrorClassification $errorClassification = null,
        public readonly int $latencyMs = 0,
    ) {
    }

    public static function success(int $latencyMs = 0): self
    {
        return new self(true, null, $latencyMs);
    }

    public static function failure(ErrorClassification $classification, int $latencyMs = 0): self
    {
        return new self(false, $classification, $latencyMs);
    }
}
