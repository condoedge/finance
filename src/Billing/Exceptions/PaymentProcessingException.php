<?php

namespace Condoedge\Finance\Billing\Exceptions;

use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Exception;

class PaymentProcessingException extends Exception
{
    public function __construct(
        public readonly PaymentContext $context,
        public readonly ?PaymentResult $result,
        string $message,
        ?\Throwable $previous = null,
        /**
         * Optional provider code so logs can attribute the failure even when
         * $result is null (e.g., the provider threw before producing a result).
         */
        public readonly ?string $providerCode = null,
    ) {
        // Note: do NOT re-throw ValidationException here. The previous version
        // did so via `throw $previous`, which discarded the outer context and
        // made provider-failure logging impossible. The caller is responsible
        // for unwrapping if it wants to surface validation errors directly.
        parent::__construct($message, 0, $previous);
    }

    public static function providerNotRegistered(string $code, ?PaymentContext $context = null): self
    {
        return new self(
            context: $context ?? throw new \LogicException('PaymentContext required'),
            result: null,
            message: "Payment provider '{$code}' not found in registry",
            providerCode: $code,
        );
    }

    public function loggingContext(): array
    {
        return [
            'team_id' => $this->context->getTeamId(),
            'payable_id' => $this->context->payable->getPayableId(),
            'payable_type' => $this->context->payable->getPayableType(),
            'payment_method' => $this->context->paymentMethod->value,
            'provider_code' => $this->providerCode ?? $this->result?->paymentProviderCode,
            'message' => $this->getMessage(),
            'previous' => $this->getPrevious()?->getMessage(),
        ];
    }
}
