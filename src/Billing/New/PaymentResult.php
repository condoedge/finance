<?php

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $transactionId,
        public readonly float $amount,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = [],
        public readonly ?PaymentActionEnum $action = null,
    ) {}
    
    public static function success(string $transactionId, float $amount, array $metadata = []): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            amount: $amount,
            metadata: $metadata
        );
    }

    public static function pending(string $transactionId, float $amount, array $metadata = [], ?PaymentActionEnum $action = null): self
    {
        return new self(
            success: false,
            transactionId: $transactionId,
            amount: $amount,
            metadata: $metadata,
            action: $action,
        );
    }
    
    public static function failed(string $errorMessage, ?string $transactionId = null): self
    {
        return new self(
            success: false,
            transactionId: $transactionId ?? '',
            amount: 0,
            errorMessage: $errorMessage
        );
    }
}