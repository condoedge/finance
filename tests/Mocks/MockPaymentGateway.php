<?php

namespace Tests\Mocks;

use Condoedge\Finance\Billing\PaymentContext;
use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Billing\PaymentResult;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Illuminate\Routing\Router;
use Kompo\Elements\BaseElement;

/**
 * Mock Payment Gateway for testing the new payment system
 */
class MockPaymentGateway implements PaymentGatewayInterface
{
    private bool $shouldSucceed = true;
    private array $responseData = [];
    private array $lastContext = [];
    private int $processCallCount = 0;
    private ?string $errorMessage = null;
    private bool $isPending = false;
    private ?string $redirectUrl = null;

    public function getCode(): string
    {
        return 'mock_gateway';
    }

    public function processPayment(PaymentContext $context): PaymentResult
    {
        $this->lastContext = (array) $context;
        $this->processCallCount++;

        // Simulate processing delay
        usleep(10000); // 10ms

        if ($this->isPending) {
            return PaymentResult::pending(
                transactionId: $this->responseData['transactionId'] ?? 'MOCK-PENDING-' . uniqid(),
                amount: $context->payable->getPayableAmount()->toFloat(),
                paymentProviderCode: $this->getCode(),
                metadata: array_merge($context->metadata, $this->responseData['metadata'] ?? []),
                redirectUrl: $this->redirectUrl
            );
        }

        if (!$this->shouldSucceed) {
            return PaymentResult::failed(
                errorMessage: $this->errorMessage ?? 'Mock payment failed',
                transactionId: $this->responseData['transactionId'] ?? 'MOCK-FAILED-' . uniqid(),
                paymentProviderCode: $this->getCode()
            );
        }

        return PaymentResult::success(
            transactionId: $this->responseData['transactionId'] ?? 'MOCK-SUCCESS-' . uniqid(),
            amount: $this->responseData['amount'] ?? $context->payable->getPayableAmount()->toFloat(),
            paymentProviderCode: $this->getCode(),
            metadata: array_merge($context->metadata, $this->responseData['metadata'] ?? [])
        );
    }

    public function getPaymentForm(PaymentContext $context): ?BaseElement
    {
        // Return a simple mock form
        return _Rows(
            _Html('Mock Payment Form'),
            _Input()->name('mock_field')->placeholder('Test field'),
            _SubmitButton('Pay with Mock')
        );
    }

    public function getSupportedPaymentMethods(): array
    {
        return [
            PaymentMethodEnum::CREDIT_CARD,
            PaymentMethodEnum::BANK_TRANSFER,
        ];
    }

    public function registerWebhookRoutes(Router $router): void
    {
        $router->post('webhooks/mock/{transactionId}', function ($transactionId) {
            // Mock webhook handler
            return response()->json(['status' => 'received', 'transactionId' => $transactionId]);
        })->name('finance.webhooks.mock');
    }

    // Test helper methods

    public function setShouldSucceed(bool $shouldSucceed): self
    {
        $this->shouldSucceed = $shouldSucceed;
        return $this;
    }

    public function setErrorMessage(string $message): self
    {
        $this->errorMessage = $message;
        return $this;
    }

    public function setPending(bool $isPending, ?string $redirectUrl = null): self
    {
        $this->isPending = $isPending;
        $this->redirectUrl = $redirectUrl ?? 'https://mock-gateway.test/redirect';
        return $this;
    }

    public function setResponseData(array $data): self
    {
        $this->responseData = $data;
        return $this;
    }

    public function getLastContext(): array
    {
        return $this->lastContext;
    }

    public function getProcessCallCount(): int
    {
        return $this->processCallCount;
    }

    public function reset(): void
    {
        $this->shouldSucceed = true;
        $this->responseData = [];
        $this->lastContext = [];
        $this->processCallCount = 0;
        $this->errorMessage = null;
        $this->isPending = false;
        $this->redirectUrl = null;
    }

    /**
     * Simulate specific scenarios for testing
     */
    public function simulateScenario(string $scenario): void
    {
        switch ($scenario) {
            case 'network_timeout':
                $this->setShouldSucceed(false)
                    ->setErrorMessage('Network timeout after 30 seconds');
                break;

            case 'insufficient_funds':
                $this->setShouldSucceed(false)
                    ->setErrorMessage('Insufficient funds')
                    ->setResponseData(['errorCode' => 'INSUFFICIENT_FUNDS']);
                break;

            case '3ds_required':
                $this->setPending(true, 'https://mock-gateway.test/3ds-verify');
                break;

            case 'fraud_detected':
                $this->setShouldSucceed(false)
                    ->setErrorMessage('Transaction flagged as fraudulent')
                    ->setResponseData([
                        'errorCode' => 'FRAUD_DETECTED',
                        'fraudScore' => 95,
                        'fraudReasons' => ['unusual_location', 'high_amount'],
                    ]);
                break;

            case 'success':
            default:
                $this->setShouldSucceed(true);
                break;
        }
    }
}
