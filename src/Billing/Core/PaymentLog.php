<?php

namespace Condoedge\Finance\Billing\Core;

use Illuminate\Support\Facades\Log;

/**
 * Structured logger for payment events. Single shape so we can ship to Loki /
 * Elastic later without touching call sites. Audit §1.2.3 — every payment log
 * must carry team, payable, provider, action, outcome, latency, reason.
 *
 * Channel 'payment-events' if configured, else 'default'.
 */
final class PaymentLog
{
    public static function attempt(PaymentContext $context, string $providerCode, string $action): void
    {
        self::write('info', 'finance.payment.attempt', [
            'team_id' => $context->getTeamId(),
            'payable_id' => $context->payable->getPayableId(),
            'payable_type' => $context->payable->getPayableType(),
            'payment_method' => $context->paymentMethod->value,
            'provider_code' => $providerCode,
            'action' => $action,
        ]);
    }

    public static function success(PaymentContext $context, string $providerCode, string $transactionId, int $latencyMs): void
    {
        self::write('info', 'finance.payment.success', [
            'team_id' => $context->getTeamId(),
            'payable_id' => $context->payable->getPayableId(),
            'payable_type' => $context->payable->getPayableType(),
            'payment_method' => $context->paymentMethod->value,
            'provider_code' => $providerCode,
            'transaction_id' => $transactionId,
            'latency_ms' => $latencyMs,
            'outcome' => 'success',
        ]);
    }

    public static function failure(
        PaymentContext $context,
        string $providerCode,
        ?ErrorClassification $classification,
        ?string $message,
        int $latencyMs,
    ): void {
        self::write('warning', 'finance.payment.failure', [
            'team_id' => $context->getTeamId(),
            'payable_id' => $context->payable->getPayableId(),
            'payable_type' => $context->payable->getPayableType(),
            'payment_method' => $context->paymentMethod->value,
            'provider_code' => $providerCode,
            'reason_code' => $classification?->reasonCode,
            'reason_category' => $classification?->category->value,
            'message' => $message,
            'latency_ms' => $latencyMs,
            'outcome' => 'failure',
        ]);
    }

    public static function unavailable(
        PaymentContext $context,
        string $reason,
        array $attemptedProviders = [],
    ): void {
        self::write('warning', 'finance.payment.unavailable', [
            'team_id' => $context->getTeamId(),
            'payable_id' => $context->payable->getPayableId(),
            'payable_type' => $context->payable->getPayableType(),
            'payment_method' => $context->paymentMethod->value,
            'reason' => $reason,
            'attempted_providers' => $attemptedProviders,
        ]);
    }

    public static function fallback(
        PaymentContext $context,
        string $fromProvider,
        string $toProvider,
        ?ErrorClassification $reason,
    ): void {
        self::write('info', 'finance.payment.fallback', [
            'team_id' => $context->getTeamId(),
            'payable_id' => $context->payable->getPayableId(),
            'from_provider' => $fromProvider,
            'to_provider' => $toProvider,
            'reason_code' => $reason?->reasonCode,
        ]);
    }

    private static function write(string $level, string $event, array $payload): void
    {
        $channel = config('logging.channels.payment-events') ? 'payment-events' : null;
        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();
        $logger->{$level}($event, $payload);
    }
}
