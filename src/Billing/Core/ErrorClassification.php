<?php

namespace Condoedge\Finance\Billing\Core;

/**
 * How the provider categorizes a thrown error. The resolver uses this to decide
 * whether to try the next provider in the chain (fallback) and whether to count
 * the failure against provider health.
 *
 * - TRANSIENT: Network blip, rate limit. Try fallback. Counts against health.
 * - AUTH: Bad credentials on our side. Try fallback. Counts against health (high).
 * - NETWORK: Connection refused / timeout. Try fallback. Counts against health.
 * - PERMANENT: Customer error (card declined, validation). Do NOT fallback; do NOT
 *              count against health — the provider is fine, the customer's card isn't.
 * - UNKNOWN: Default fallback when classifier can't decide. Conservative: try fallback,
 *            count against health.
 */
enum ErrorClassificationCategory: string
{
    case TRANSIENT = 'transient';
    case AUTH = 'auth';
    case NETWORK = 'network';
    case PERMANENT = 'permanent';
    case UNKNOWN = 'unknown';

    public function shouldFallback(): bool
    {
        return $this !== self::PERMANENT;
    }

    public function affectsHealth(): bool
    {
        return $this !== self::PERMANENT;
    }
}

final class ErrorClassification
{
    public function __construct(
        public readonly ErrorClassificationCategory $category,
        public readonly string $reasonCode,
        public readonly ?string $message = null,
    ) {
    }

    public static function transient(string $reasonCode = 'transient', ?string $message = null): self
    {
        return new self(ErrorClassificationCategory::TRANSIENT, $reasonCode, $message);
    }

    public static function authFailure(string $reasonCode = 'auth_failure', ?string $message = null): self
    {
        return new self(ErrorClassificationCategory::AUTH, $reasonCode, $message);
    }

    public static function network(string $reasonCode = 'network', ?string $message = null): self
    {
        return new self(ErrorClassificationCategory::NETWORK, $reasonCode, $message);
    }

    public static function permanent(string $reasonCode = 'permanent', ?string $message = null): self
    {
        return new self(ErrorClassificationCategory::PERMANENT, $reasonCode, $message);
    }

    public static function unknown(?string $message = null): self
    {
        return new self(ErrorClassificationCategory::UNKNOWN, 'unknown', $message);
    }

    public function shouldFallback(): bool
    {
        return $this->category->shouldFallback();
    }

    public function affectsHealth(): bool
    {
        return $this->category->affectsHealth();
    }
}
