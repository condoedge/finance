<?php

namespace Condoedge\Finance\Billing\Exceptions;

use Condoedge\Finance\Models\PaymentMethodEnum;

/**
 * Raised when the resolver cannot find any healthy provider for a context.
 *
 * Three subcases via static factories:
 *  - noneConfigured: no provider rows for (team, method) and no config fallback.
 *  - allDown: providers exist but health checker reports all DOWN.
 *  - allFailed: chain was attempted but every provider raised a fallback-eligible error.
 *
 * Callers decide how to surface (Kompo notice for UI, 503 for API).
 */
class NoProviderAvailableException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?int $teamId,
        public readonly ?PaymentMethodEnum $paymentMethod,
        public readonly array $context = [],
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "No payment provider available ({$reason})",
            0,
            $previous,
        );
    }

    public static function noneConfigured(?int $teamId, PaymentMethodEnum $method): self
    {
        return new self(
            reason: 'none_configured',
            teamId: $teamId,
            paymentMethod: $method,
            message: "No payment provider configured for team {$teamId} method {$method->value}",
        );
    }

    public static function allDown(?int $teamId, PaymentMethodEnum $method, array $providerCodes): self
    {
        return new self(
            reason: 'all_down',
            teamId: $teamId,
            paymentMethod: $method,
            context: ['provider_codes' => $providerCodes],
            message: 'All configured payment providers are currently unavailable',
        );
    }

    public static function allFailed(?int $teamId, ?PaymentMethodEnum $method, array $errors, ?\Throwable $previous = null): self
    {
        return new self(
            reason: 'all_failed',
            teamId: $teamId,
            paymentMethod: $method,
            context: ['errors' => $errors],
            message: 'All payment provider attempts failed',
            previous: $previous,
        );
    }
}
