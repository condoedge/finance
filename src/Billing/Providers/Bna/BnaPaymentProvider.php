<?php

namespace Condoedge\Finance\Billing\Providers\Bna;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;
use Condoedge\Finance\Billing\Core\PaymentActionEnum;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Condoedge\Finance\Billing\Core\WebhookProcessor;
use Condoedge\Finance\Billing\Providers\Bna\Form\PaymentCreditCardForm;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kompo\Elements\BaseElement;

class BnaPaymentProvider implements PaymentGatewayInterface
{
    use \Condoedge\Finance\Billing\Traits\RegistersWebhooks;

    protected string $apiUrl;
    protected string $accessKey;
    protected string $secretKey;

    /**
     * Current payment context
     */
    protected ?PaymentContext $paymentContext = null;

    /**
     * API response data
     */
    protected ?array $apiResponse = null;

    public function __construct()
    {
        $this->apiUrl = config('kompo-finance.services.bna_payment_provider.api_url');
        $this->accessKey = config('kompo-finance.services.bna_payment_provider.api_key');
        $this->secretKey = config('kompo-finance.services.bna_payment_provider.api_secret');
    }

    public function getCode(): string
    {
        return 'bna';
    }

    public function getSupportedPaymentMethods(): array
    {
        return [
            PaymentMethodEnum::CREDIT_CARD,
            PaymentMethodEnum::INTERAC,
        ];
    }

    public function getPaymentForm(PaymentContext $context): ?BaseElement
    {
        return match($context->paymentMethod) {
            PaymentMethodEnum::CREDIT_CARD => new PaymentCreditCardForm(),
            PaymentMethodEnum::INTERAC => null, // Interac redirects directly
            default => throw new \InvalidArgumentException(
                'Unsupported payment method: ' . $context->paymentMethod->label()
            ),
        };
    }

    public function processPayment(PaymentContext $context): PaymentResult
    {
        $this->paymentContext = $context;

        try {
            return match($context->paymentMethod) {
                PaymentMethodEnum::CREDIT_CARD => $this->processCreditCardPayment($context),
                PaymentMethodEnum::INTERAC => $this->processInteracPayment($context),
                default => throw new \InvalidArgumentException(
                    'Unsupported payment method: ' . $context->paymentMethod->label()
                ),
            };
        } catch (\Exception $e) {
            Log::error('BNA payment processing failed', [
                'error' => $e->getMessage(),
                'context' => [
                    'payable_id' => $context->payable->getPayableId(),
                    'payable_type' => $context->payable->getPayableType(),
                    'payment_method' => $context->paymentMethod->value,
                ]
            ]);

            throw $e;
        }
    }

    /**
     * Process credit card payment
     */
    protected function processCreditCardPayment(PaymentContext $context): PaymentResult
    {
        // Validate input
        $this->validateCreditCardInput($context->paymentData);

        // Make API request
        $response = $this->createSaleRequest('card', $context);

        if (!$response->successful()) {
            Log::error('BNA API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return PaymentResult::failed(
                errorMessage: 'Payment provider error: ' . $response->status(),
                paymentProviderCode: $this->getCode()
            );
        }

        $this->apiResponse = $response->json();

        // Check payment status
        if ($this->isPaymentSuccessful()) {
            return PaymentResult::success(
                transactionId: $this->getResponseData('referenceUUID'),
                amount: $this->getResponseData('amount'),
                paymentProviderCode: $this->getCode()
            );
        }

        return PaymentResult::failed(
            errorMessage: $this->getResponseData('errorMessage') ?? __('error-payment-declined'),
            transactionId: $this->getResponseData('referenceUUID'),
            paymentProviderCode: $this->getCode()
        );
    }

    /**
     * Process Interac payment
     */
    protected function processInteracPayment(PaymentContext $context): PaymentResult
    {
        // Make API request
        $response = $this->createSaleRequest('e-transfer', $context);

        if (!$response->successful()) {
            Log::error('BNA Interac API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return PaymentResult::failed(
                errorMessage: 'Payment provider error: ' . $response->status(),
                paymentProviderCode: $this->getCode()
            );
        }

        $this->apiResponse = $response->json();

        $interacUrl = $this->getResponseData('interacUrl');

        if (!$interacUrl) {
            return PaymentResult::failed(
                errorMessage: __('error-finance-interac-url-not-found'),
                transactionId: $this->getResponseData('referenceUUID'),
                paymentProviderCode: $this->getCode()
            );
        }

        return PaymentResult::pending(
            transactionId: $this->getResponseData('referenceUUID'),
            amount: $this->getResponseData('amount'),
            paymentProviderCode: $this->getCode(),
            action: PaymentActionEnum::REDIRECT,
            redirectUrl: $interacUrl
        );
    }

    /**
     * Create sale API request
     */
    protected function createSaleRequest(string $type, PaymentContext $context)
    {
        $endpoint = "/v1/transaction/{$type}/sale";

        $requestData = [
            'transactionTime' => now()->toIso8601String(),
            'applyFee' => false,
            'currency' => 'CAD',
            'customerInfo' => $this->prepareCustomerInfo($context),
            'metadata' => $context->toProviderMetadata(),
        ];

        // Add line items
        $lineItems = $this->prepareLineItems($context);
        $requestData['items'] = $lineItems['items'];
        $requestData['subtotal'] = $lineItems['subtotal'];

        // Add payment details for card payments
        if ($type === 'card') {
            $requestData['paymentDetails'] = $this->preparePaymentDetails($context);
        }

        Log::info('BNA API request', [
            'endpoint' => $endpoint,
            'type' => $type,
            'subtotal' => $requestData['subtotal']
        ]);

        return Http::withBasicAuth($this->accessKey, $this->secretKey)
            ->timeout(30)
            ->post($this->apiUrl . $endpoint, $requestData);
    }

    /**
     * Prepare customer info for API
     */
    protected function prepareCustomerInfo(PaymentContext $context): array
    {
        $payable = $context->payable;
        $address = $payable->getAddress();

        if (!$address) {
            throw new \InvalidArgumentException(__('error-finance-missing-address'));
        }

        // Get customer name
        $customerName = $context->paymentData['complete_name']
            ?? $payable->getCustomerName()
            ?? 'Unknown Customer';

        $nameParts = $this->parseCustomerName($customerName);

        return [
            'email' => $payable->getEmail() ?? 'unknown@example.com',
            'firstName' => $nameParts['first'],
            'lastName' => $nameParts['last'],
            'type' => 'Personal',
            'address' => array_filter([
                'postalCode' => $address->postal_code,
                'streetNumber' => $address->street_number ?? '',
                'streetName' => sanitizeString($address->address1 ?? ''),
                'city' => $address->city ?? '',
                'country' => normalizeCountryCode($address->country ?? 'Canada'),
            ])
        ];
    }

    /**
     * Prepare line items
     */
    protected function prepareLineItems(PaymentContext $context): array
    {
        $payableLines = $context->payable->getPayableLines();
        $decimalScale = config('kompo-finance.payment-related-decimal-scale', 2);

        $items = [];
        $itemsTotal = 0;

        foreach ($payableLines as $line) {
            $amount = $line->amount->floor($decimalScale)->toFloat();
            $items[] = [
                'description' => Str::limit($line->description, 100),
                'sku' => $line->sku ?? 'ITEM',
                'price' => $line->price->floor($decimalScale)->toFloat(),
                'quantity' => $line->quantity ?? 1,
                'amount' => $amount,
            ];
            $itemsTotal += $amount;
        }

        $subtotal = collect($payableLines)
            ->sumDecimals('amount')
            ->round($decimalScale)
            ->toFloat();

        $items = $this->createAdjustmentItemIfRequired($items, $subtotal);

        return [
            'items' => $items,
            'subtotal' => (string) $subtotal
        ];
    }

    protected function createAdjustmentItemIfRequired($items, $subtotal)
    {
        $itemsTotal = collect($items)->sum('amount');

        if ($itemsTotal !== $subtotal) {
            $items[] = [
                'description' => __('finance-minor-adjustment'),
                'sku' => 'ADJUSTMENT',
                'price' => round($subtotal - $itemsTotal, 2),
                'quantity' => 1,
                'amount' => round($subtotal - $itemsTotal, 2),
            ];
        }

        return $items;
    }

    /**
     * Prepare payment details for credit card
     */
    protected function preparePaymentDetails(PaymentContext $context): array
    {
        $data = $context->paymentData;

        // Parse expiration date (MM/YY format)
        $expiry = carbon($data['expiration_date'] ?? '', 'd/m/Y');

        $month = $expiry->format('m');
        $year = $expiry->format('y');

        return [
            'cardNumber' => preg_replace('/\s+/', '', $data['card_information']),
            'cardHolder' => $data['complete_name'],
            'cardType' => 'credit',
            'cardIdNumber' => $data['card_cvc'],
            'expiryMonth' => $month,
            'expiryYear' => $year,
        ];
    }

    /**
     * Validate credit card input
     */
    protected function validateCreditCardInput(array $data): void
    {
        $validator = Validator::make($data, [
            'complete_name' => 'required|string|min:3|max:255',
            'card_information' => 'required|string|min:13|max:19',
            'card_cvc' => 'required|string|digits_between:3,4',
            'expiration_date' => ['required', 'string', 'date'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * Check if payment was successful
     */
    protected function isPaymentSuccessful(): bool
    {
        $status = $this->getResponseData('status');
        $errorCode = $this->getResponseData('errorCode');

        return $status === 'APPROVED' && empty($errorCode);
    }

    /**
     * Get data from API response
     */
    protected function getResponseData(string $key, $default = null)
    {
        if (!$this->apiResponse) {
            return $default;
        }

        // Support nested keys with dot notation
        $keys = explode('.', $key);
        $data = $this->apiResponse;

        foreach ($keys as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Parse customer name into first/last
     */
    protected function parseCustomerName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [
            'first' => $parts[0] ?? 'Unknown',
            'last' => $parts[1] ?? 'Customer',
        ];
    }

    /**
     * Get webhook processor
     */
    protected function getWebhookProcessor(): WebhookProcessor
    {
        return new BnaWebhookProcessor($this->secretKey);
    }
}
