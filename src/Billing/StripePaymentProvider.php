<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Billing\Kompo\PaymentCreditCardForm;
use Condoedge\Finance\Billing\Kompo\PaymentCanadianBankForm;
use Condoedge\Finance\Billing\Kompo\StripeCreditCardForm;
use Condoedge\Finance\Billing\Webhooks\RegistersWebhooks;
use Condoedge\Finance\Billing\Webhooks\StripeWebhookProcessor;
use Condoedge\Finance\Billing\Webhooks\WebhookProcessor;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kompo\Elements\BaseElement;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\StripeClient;

class StripePaymentProvider implements PaymentGatewayInterface
{
    use RegistersWebhooks;
    
    protected StripeClient $stripe;
    protected string $webhookSecret;
    
    /**
     * Current payment context
     */
    protected ?PaymentContext $paymentContext = null;
    
    public function __construct()
    {
        $this->webhookSecret = config('kompo-finance.services.stripe.webhook_secret');

        $apiKey = config('kompo-finance.services.stripe.secret_key');

        if (!$apiKey) {
            Log::critical('Stripe API key is not configured.');
            return;
        }

        $this->stripe = new StripeClient([
            'api_key' => $apiKey,
        ]);
    }
    
    public function getCode(): string
    {
        return 'stripe';
    }
    
    public function getSupportedPaymentMethods(): array
    {
        return [
            PaymentMethodEnum::CREDIT_CARD,
            PaymentMethodEnum::BANK_TRANSFER,
        ];
    }
    
    public function getPaymentForm(PaymentContext $context): ?BaseElement
    {
        return match($context->paymentMethod) {
            PaymentMethodEnum::CREDIT_CARD => new StripeCreditCardForm(),
            PaymentMethodEnum::BANK_TRANSFER => new PaymentCanadianBankForm(),
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
                PaymentMethodEnum::BANK_TRANSFER => $this->processBankTransferPayment($context),
                default => throw new \InvalidArgumentException(
                    'Unsupported payment method: ' . $context->paymentMethod->label()
                ),
            };
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode(),
                'context' => [
                    'payable_id' => $context->payable->getPayableId(),
                    'payable_type' => $context->payable->getPayableType(),
                    'payment_method' => $context->paymentMethod->value,
                ]
            ]);

            return PaymentResult::failed(
                errorMessage: $this->getReadableErrorMessage($e),
                paymentProviderCode: $this->getCode()
            );
        } catch (\Exception $e) {
            Log::error('Stripe payment processing failed', [
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
     * Process credit card payment using Payment Intents
     */
    protected function processCreditCardPayment(PaymentContext $context): PaymentResult
    {
        // Validate input
        $this->validateCreditCardInput($context->paymentData);
        
        // Create payment intent
        $paymentIntent = $this->createPaymentIntent($context);

        // Confirm the payment intent
        $confirmedIntent = $this->confirmPaymentIntent($paymentIntent, $context->paymentData['payment_method_id']);

        // Handle the result based on status
        return $this->handlePaymentIntentResult($confirmedIntent);
    }
    
    /**
     * Process bank payment (Canadian debit)
     */
    protected function processBankTransferPayment(PaymentContext $context): PaymentResult
    {
        // Validate bank account input
        $this->validateCanadianBankInput($context->paymentData);
        
        // Create payment intent for Canadian debit
        $paymentIntent = $this->createPaymentIntent($context, [
            'payment_method_types' => ['acss_debit'],
            'currency' => 'cad',
            'payment_method_options' => [
                'acss_debit' => [
                    'mandate_options' => [
                        'payment_schedule' => 'sporadic',
                        'transaction_type' => 'personal',
                    ],
                ],
            ],
        ]);

        // Create payment method for ACSS debit
        $paymentMethod = $this->createAcssPaymentMethod($context);

        // Confirm the payment intent
        $confirmedIntent = $this->confirmPaymentIntent($paymentIntent, $paymentMethod);

        // Handle the result
        return $this->handlePaymentIntentResult($confirmedIntent);
    }
    
    /**
     * Create payment intent
     */
    protected function createPaymentIntent(PaymentContext $context, array $additionalParams = []): PaymentIntent
    {
        $amount = $context->payable->getPayableAmount();
        $amountInCents = (int) ($amount->toFloat() * 100);
        
        $params = array_merge([
            'amount' => $amountInCents,
            'currency' => 'cad',
            'payment_method_types' => ['card'],
            'metadata' => $context->toProviderMetadata(),
            'description' => Str::limit($context->payable->getPaymentDescription(), 500),
            'statement_descriptor' => $this->getStatementDescriptor(),
        ], $additionalParams);
        
        // Add customer email if available
        if ($email = $context->payable->getEmail()) {
            $params['receipt_email'] = $email;
        }
        
        // Add return URL if provided
        if ($context->returnUrl) {
            $params['return_url'] = $context->returnUrl;
        }
        
        try {
            return $this->stripe->paymentIntents->create($params);
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe payment intent', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }
    
    /**
     * Create card payment method
     */
    protected function createCardPaymentMethod(PaymentContext $context): PaymentMethod
    {
        $data = $context->paymentData;
        
        // Parse expiration date
        $expiry = carbon($data['expiration_date'] ?? '', 'd/m/Y');
        
        // Ensure 4-digit year
        if ($expiry->year < 100) {
            $expiry->year += 2000;
        }
        
        $params = [
            'type' => 'card',
            'card' => [
                'number' => preg_replace('/\s+/', '', $data['card_information']),
                'exp_month' => $expiry->format('m'),
                'exp_year' => $expiry->format('y'),
                'cvc' => $data['card_cvc'],
            ],
        ];
        
        // Add billing details
        $this->addBillingDetails($params, $context, $data['complete_name']);
        
        try {
            return $this->stripe->paymentMethods->create($params);
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe payment method', [
                'error' => $e->getMessage(),
                'type' => 'card'
            ]);
            throw $e;
        }
    }
    
    /**
     * Create ACSS debit payment method
     */
    protected function createAcssPaymentMethod(PaymentContext $context): PaymentMethod
    {
        $data = $context->paymentData;
        
        $params = [
            'type' => 'acss_debit',
            'acss_debit' => [
                'account_number' => $data['account_number'],
                'institution_number' => $data['institution_number'],
                'transit_number' => $data['transit_number'],
            ],
        ];
        
        // Add billing details
        $accountHolderName = $data['account_holder_name'] ?? $context->payable->getCustomerName();
        $this->addBillingDetails($params, $context, $accountHolderName);
        
        try {
            return $this->stripe->paymentMethods->create($params);
        } catch (ApiErrorException $e) {
            Log::error('Failed to create ACSS payment method', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Add billing details to payment method params
     */
    protected function addBillingDetails(array &$params, PaymentContext $context, ?string $name): void
    {
        if (!$name && !$context->payable->getEmail()) {
            return;
        }
        
        $params['billing_details'] = [];
        
        if ($name) {
            $params['billing_details']['name'] = $name;
        }
        
        if ($email = $context->payable->getEmail()) {
            $params['billing_details']['email'] = $email;
        }
        
        if ($address = $context->payable->getAddress()) {
            $params['billing_details']['address'] = [
                'line1' => trim($address->street_number . ' ' . $address->address1),
                'city' => $address->city,
                'postal_code' => $address->postal_code,
                'country' => normalizeCountryCode($address->country),
            ];
            
            if ($address->address2) {
                $params['billing_details']['address']['line2'] = $address->address2;
            }
            
            if ($address->state) {
                $params['billing_details']['address']['state'] = $address->state;
            }
        }
    }
    
    /**
     * Confirm payment intent
     */
    protected function confirmPaymentIntent(PaymentIntent $paymentIntent, string $paymentMethodId): PaymentIntent
    {
        $params = [
            'payment_method' => $paymentMethodId,
            'mandate_data' => [
                'customer_acceptance' => [
                    'type' => 'online',
                    'online' => [
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ],
                ],
            ],
        ];
        
        // Add return URL for 3D Secure or other redirects
        if ($this->paymentContext->returnUrl) {
            $params['return_url'] = $this->paymentContext->returnUrl;
        } else {
            // Fallback to webhook URL
            $params['return_url'] = route('finance.webhooks.stripe.payment');
        }
        
        try {
            return $this->stripe->paymentIntents->confirm($paymentIntent->id, $params);
        } catch (ApiErrorException $e) {
            Log::error('Failed to confirm Stripe payment intent', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $paymentIntent->id
            ]);
            throw $e;
        }
    }
    
    /**
     * Handle payment intent result
     */
    protected function handlePaymentIntentResult(PaymentIntent $paymentIntent): PaymentResult
    {
        switch ($paymentIntent->status) {
            case PaymentIntent::STATUS_SUCCEEDED:
                return PaymentResult::success(
                    transactionId: $paymentIntent->id,
                    amount: $paymentIntent->amount / 100,
                    paymentProviderCode: $this->getCode(),
                    metadata: $paymentIntent->metadata->toArray()
                );
                
            case PaymentIntent::STATUS_REQUIRES_ACTION:
                // Handle 3D Secure or other authentication required
                $nextAction = $paymentIntent->next_action;
                $type = $nextAction->type;
                $redirectUrl = null;

                switch ($type) {
                    case 'verify_with_microdeposits':
                        $redirectUrl = $nextAction->verify_with_microdeposits->hosted_verification_url;
                        break;
                    default:
                        Log::warning('Unknown next action type', ['type' => $type]);
                        $redirectUrl = null;
                }

                return PaymentResult::pending(
                    transactionId: $paymentIntent->id,
                    amount: $paymentIntent->amount / 100,
                    paymentProviderCode: $this->getCode(),
                    action: $redirectUrl ? PaymentActionEnum::REDIRECT : null,
                    redirectUrl: $redirectUrl
                );
            case PaymentIntent::STATUS_REQUIRES_CONFIRMATION:
                // 3D Secure or other authentication required
                $redirectUrl = $paymentIntent->next_action?->redirect_to_url?->url;
                
                return PaymentResult::pending(
                    transactionId: $paymentIntent->id,
                    amount: $paymentIntent->amount / 100,
                    paymentProviderCode: $this->getCode(),
                    metadata: array_merge(
                        $this->paymentContext->payable->getPaymentMetadata(),
                        $this->paymentContext->metadata
                    ),
                    action: $redirectUrl ? PaymentActionEnum::REDIRECT : null,
                    redirectUrl: $redirectUrl
                );
                
            case PaymentIntent::STATUS_PROCESSING:
                // Payment is being processed
                return PaymentResult::pending(
                    transactionId: $paymentIntent->id,
                    amount: $paymentIntent->amount / 100,
                    paymentProviderCode: $this->getCode(),
                    metadata: array_merge(
                        $this->paymentContext->payable->getPaymentMetadata(),
                        $this->paymentContext->metadata
                    )
                );
                
            default:
                // Payment failed
                $error = $paymentIntent->last_payment_error;
                return PaymentResult::failed(
                    errorMessage: $error?->message ?? __('translate.finance-payment-failed'),
                    transactionId: $paymentIntent->id,
                    paymentProviderCode: $this->getCode()
                );
        }
    }
    
    /**
     * Get statement descriptor
     */
    protected function getStatementDescriptor(): string
    {
        $descriptor = config('app.name', 'Payment');
        // Stripe allows max 22 characters, alphanumeric only
        $descriptor = preg_replace('/[^a-zA-Z0-9 ]/', '', $descriptor);
        return Str::limit($descriptor, 22);
    }
    
    /**
     * Get readable error message from Stripe exception
     */
    protected function getReadableErrorMessage(ApiErrorException $e): string
    {
        // Common Stripe error codes with user-friendly messages
        $errorMessages = [
            'card_declined' => __('translate.finance-card-declined'),
            'expired_card' => __('translate.finance-card-expired'),
            'incorrect_cvc' => __('translate.finance-incorrect-cvc'),
            'processing_error' => __('translate.finance-processing-error'),
            'incorrect_number' => __('translate.finance-incorrect-card-number'),
            'insufficient_funds' => __('translate.finance-insufficient-funds'),
            'invalid_account' => __('translate.finance-invalid-bank-account'),
        ];
        
        $code = $e->getStripeCode();
        
        if (isset($errorMessages[$code])) {
            return $errorMessages[$code];
        }
        
        // Fallback to Stripe's message or generic error
        return $e->getMessage() ?: __('translate.finance-payment-failed');
    }
    
    /**
     * Validate credit card input
     */
    protected function validateCreditCardInput(array $data): void
    {
        $validator = Validator::make($data, [
            'complete_name' => 'required|string|min:3|max:255',
            'payment_method_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }
    
    /**
     * Validate Canadian bank account input
     */
    protected function validateCanadianBankInput(array $data): void
    {
        $validator = Validator::make($data, [
            'account_holder_name' => 'required|string|min:2|max:255',
            'transit_number' => 'required|digits:5',
            'institution_number' => 'required|digits:3',
            'account_number' => 'required|digits_between:7,12',
            'authorize_debit' => 'required|accepted',
        ]);
        
        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                'Invalid bank account data: ' . $validator->errors()->first()
            );
        }
    }
    
    /**
     * Get webhook processor
     */
    protected function getWebhookProcessor(): WebhookProcessor
    {
        return new StripeWebhookProcessor($this->webhookSecret);
    }
}
