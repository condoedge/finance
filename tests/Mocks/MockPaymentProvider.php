<?php

namespace Tests\Mocks;

use Condoedge\Finance\Billing\AbstractPaymentProvider;

/**
 * Mock Payment Provider for testing
 *
 * This mock provider can simulate various payment scenarios including
 * successful payments, failures, and different response types.
 */
class MockPaymentProvider extends AbstractPaymentProvider
{
    private $shouldSucceed = true;
    private $responseData = [];
    private $saleCreated = false;
    private $context = [];

    /**
     * Set whether the payment should succeed
     */
    public function setShouldSucceed(bool $shouldSucceed): void
    {
        $this->shouldSucceed = $shouldSucceed;
    }

    /**
     * Set custom response data
     */
    public function setResponseData(array $data): void
    {
        $this->responseData = $data;
    }

    /**
     * Check if sale was created
     */
    public function wasSaleCreated(): bool
    {
        return $this->saleCreated;
    }

    /**
     * Get the context that was set
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Initialize context (called by resolver)
     */
    public function initializeContext(array $context = []): void
    {
        parent::initializeContext($context);
        $this->context = $context;
    }

    /**
     * Get data from response using dot notation
     */
    protected function getDataFromResponse($key, $data = null)
    {
        $data = $data ?: $this->saleResponse ?: $this->responseData;

        if (isset($data[$key])) {
            return $data[$key];
        }

        // Handle nested keys with dot notation
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (isset($data[$k])) {
                $data = $data[$k];
            } else {
                return null;
            }
        }

        return $data;
    }

    /**
     * Create a sale (simulate API call)
     */
    public function createSale($request, $onSuccess = null)
    {
        $this->ensureInvoiceIsSet();

        $this->saleRequest = $request;
        $this->saleCreated = true;

        // Default response based on shouldSucceed flag
        $defaultResponse = $this->shouldSucceed ? [
            'status' => 'APPROVED',
            'amount' => $request['amount'] ?? ($this->invoice ? $this->invoice->invoice_total_amount->toFloat() : 100),
            'referenceUUID' => 'TEST-' . uniqid(),
            'errorCode' => null,
            'transactionId' => 'TXN-' . uniqid(),
            'timestamp' => now()->toIso8601String(),
        ] : [
            'status' => 'DECLINED',
            'amount' => 0,
            'referenceUUID' => null,
            'errorCode' => 'INSUFFICIENT_FUNDS',
            'errorMessage' => 'The transaction was declined due to insufficient funds',
            'declineReason' => 'Insufficient funds in account',
        ];

        // Merge with custom response data (custom data takes precedence)
        $this->saleResponse = array_merge($defaultResponse, $this->responseData);
    }

    /**
     * Check if payment was successful
     */
    public function checkIfPaymentWasSuccessful()
    {
        return $this->shouldSucceed &&
               $this->getDataFromResponse('status') === 'APPROVED' &&
               !$this->getDataFromResponse('errorCode');
    }

    /**
     * Reset the mock provider state
     */
    public function reset(): void
    {
        $this->shouldSucceed = true;
        $this->responseData = [];
        $this->saleCreated = false;
        $this->saleResponse = null;
        $this->saleRequest = null;
        $this->context = [];
        $this->invoice = null;
        $this->installment_ids = null;
    }

    /**
     * Simulate specific scenarios
     */
    public function simulateScenario(string $scenario): void
    {
        switch ($scenario) {
            case 'network_timeout':
                $this->setShouldSucceed(false);
                $this->setResponseData([
                    'status' => null,
                    'errorCode' => 'NETWORK_TIMEOUT',
                    'errorMessage' => 'Request timed out after 30 seconds',
                ]);
                break;

            case 'fraud_detected':
                $this->setShouldSucceed(false);
                $this->setResponseData([
                    'status' => 'DECLINED',
                    'errorCode' => 'FRAUD_DETECTED',
                    'errorMessage' => 'Transaction flagged as potentially fraudulent',
                    'fraudScore' => 95,
                    'fraudReasons' => ['unusual_location', 'high_amount', 'new_card'],
                ]);
                break;

            case '3ds_required':
                $this->setShouldSucceed(false);
                $this->setResponseData([
                    'status' => 'PENDING_3DS',
                    'errorCode' => '3DS_REQUIRED',
                    'requires3DS' => true,
                    '3dsUrl' => 'https://test.3ds.com/verify',
                    'sessionId' => 'SES-' . uniqid(),
                ]);
                break;

            case 'partial_approval':
                $this->setShouldSucceed(true);
                $this->setResponseData([
                    'status' => 'APPROVED',
                    'amount' => 500, // Will be overridden with partial amount
                    'approvedAmount' => 300,
                    'requestedAmount' => 500,
                    'partialApproval' => true,
                ]);
                break;

            case 'expired_card':
                $this->setShouldSucceed(false);
                $this->setResponseData([
                    'status' => 'DECLINED',
                    'errorCode' => 'EXPIRED_CARD',
                    'errorMessage' => 'The card has expired',
                    'cardExpiry' => '12/23',
                ]);
                break;

            default:
                // Default successful scenario
                $this->setShouldSucceed(true);
                $this->setResponseData([]);
        }
    }
}
