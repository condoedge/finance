<?php
namespace Condoedge\Finance\Billing;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $transactionId,
        public readonly string $paymentProviderCode = '',
        public readonly float $amount,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = [],
        public readonly ?PaymentActionEnum $action = null,
        public readonly ?string $redirectUrl = null, // URL to redirect for pending payments
        public readonly ?bool $isPending = false, // Indicates if the payment is pending
    ) {}

    public static function success(string $transactionId, float $amount, string $paymentProviderCode = '', array $metadata = []): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            paymentProviderCode: $paymentProviderCode,
            amount: $amount,
            metadata: $metadata
        );
    }

    public static function pending(string $transactionId, float $amount, string $paymentProviderCode = '', array $metadata = [], ?PaymentActionEnum $action = null, ?string $redirectUrl = null): self
    {
        return new self(
            success: false,
            transactionId: $transactionId,
            paymentProviderCode: $paymentProviderCode,
            amount: $amount,
            metadata: $metadata,
            action: $action,
            isPending: true,
            redirectUrl: $redirectUrl
        );
    }

    public static function failed(string $errorMessage, ?string $transactionId = null, string $paymentProviderCode = ''): self
    {
        return new self(
            success: false,
            transactionId: $transactionId ?? '',
            amount: 0,
            errorMessage: $errorMessage,
            paymentProviderCode: $paymentProviderCode
        );
    }

    public function isSuccessful(): bool
    {
        return $this->success && !$this->isPending;
    }

    public function executeAction(): mixed
    {
        if ($this->action) {
            return $this->action->execute($this);
        }

        if ($this->redirectUrl) {
            return redirect()->to($this->redirectUrl);
        }

        return null;
    }

    public function executeActionIntoKompoPanel(): mixed
    {
        if ($this->action) {
            return $this->action->executeIntoKompoPanel($this);
        }

        if ($this->redirectUrl) {
            return PaymentActionEnum::REDIRECT->executeIntoKompoPanel($this);
        }

        return null;
    }
}