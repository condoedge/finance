<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Billing\Kompo\PaymentCreditCardForm;
use Condoedge\Finance\Facades\PaymentProcessor;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Kompo\Elements\BaseElement;
use Transliterator;

class BnaPaymentProvider implements PaymentGatewayInterface
{
    use EmptyWebhooks;

    protected $apiUrl;

    protected $accessKey;
    protected $secretKey;
    protected $credentials;

    protected $saleRequest;
    protected $paymentData;
    protected $saleResponse;

    /**
     * @var  PaymentContext
     */
    protected $paymentContext;

    public function __construct()
    {
        $this->apiUrl = config('kompo-finance.services.bna_payment_provider.api_url', 'https://stage-api-service.bnasmartpayment.com');

        $this->accessKey = config('kompo-finance.services.bna_payment_provider.api_key', '');
        $this->secretKey = config('kompo-finance.services.bna_payment_provider.api_secret', '');

        $this->credentials = base64_encode($this->accessKey.': '.$this->secretKey);
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
            PaymentMethodEnum::INTERAC => null,
            default => throw new \Exception('Unsupported payment method: ' . $context->paymentMethod),
        };
    }

    public function processPayment(PaymentContext $context): PaymentResult
    {
        $this->paymentContext = $context;

        return match($context->paymentMethod) {
            PaymentMethodEnum::CREDIT_CARD => $this->processCreditCardSale($context),
            PaymentMethodEnum::INTERAC => $this->processInteracSale($context),
            default => throw new \Exception('Unsupported payment method: ' . $context->paymentMethod),
        };
    }

    protected function processInteracSale(PaymentContext $context): PaymentResult
    {
        $this->saleRequest = $context->paymentData;

        $response = $this->createPaymentApiRequest('e-transfer');

        $this->saleResponse = json_decode($response->body(), true);

        return PaymentResult::pending(
            $this->getDataFromResponse('referenceUUID'),
            $this->getDataFromResponse('amount'),
            metadata: $this->paymentContext->payable->getPaymentMetadata() + $this->paymentContext->metadata,
            action: PaymentActionEnum::REDIRECT,
            redirectUrl: $this->getDataFromResponse('interacUrl'),
            paymentProviderCode: $this->getCode()
        );
    }

    protected function processCreditCardSale(PaymentContext $context): PaymentResult
    {
        $this->saleRequest = $context->paymentData;

        Validator::make($this->saleRequest, [
            'complete_name' => 'required|string|max:255',
            'card_information' => 'required|string|max:20',
            'card_cvc' => 'required|string|max:4',
            'expiration_date' => 'required|string|max:5',
        ])->validate();

        $response = $this->createPaymentApiRequest();

        $this->saleResponse = json_decode($response->body(), true);

        if ($this->checkIfPaymentWasSuccessful()) {
            return PaymentResult::success(
                transactionId: $this->getDataFromResponse('referenceUUID'),
                amount: $this->getDataFromResponse('amount'),
                paymentProviderCode: $this->getCode()
            );
        } else {
            return PaymentResult::failed(
                errorMessage: $this->getDataFromResponse('errorCode'),
                transactionId: $this->getDataFromResponse('referenceUUID'),
                paymentProviderCode: $this->getCode()
            );
        }
    }

    protected function createPaymentApiRequest($type = 'card')
    {
        return $this->createSaleApiRequest($type);
    }

    protected function createSaleApiRequest($type = 'card')
    {
        $specificUrl = $this->getApiUrl('/v1/transaction/' . $type . '/sale');

        $this->paymentData = [
            'transactionTime' => carbon(now())->format('c'),
            'applyFee' => false,
            'currency' => 'CAD',
        ];

        $this->paymentData['customerInfo'] = $this->prepareCustomerInfo();

        $payableLines = $this->paymentContext->payable->getPayableLines();

        $this->paymentData['subtotal'] = (string) collect($payableLines)->sumDecimals('amount')->round(config('kompo-finance.payment-related-decimal-scale'));
        $this->paymentData['items'] = collect($payableLines)->map(function ($od) {
            return [
                'description' => $od->description,
                'sku' => $od->sku,
                'price' => $od->price->floor(config('kompo-finance.payment-related-decimal-scale'))->toFloat(),
                'quantity' => $od->quantity,
                'amount' => $od->amount->floor(config('kompo-finance.payment-related-decimal-scale'))->toFloat(),
            ];
        })->toArray();

        $this->createAdjustmentItemIfRequired();

        if ($type == 'card') {
            $this->paymentData['paymentDetails'] = $this->preparePaymentDetailsInfo();
        }

        $this->paymentData['metadata'] = $this->prepareMetaDataInfo();

        Log::warning('paymentData', $this->paymentData);

        return $this->postToApi($specificUrl, $this->paymentData);
    }

    protected function createAdjustmentItemIfRequired()
    {
        $itemsTotal = collect($this->paymentData['items'])->sum('amount');

        if ($itemsTotal !== $this->paymentData['subtotal']) {
            $this->paymentData['items'][] = [
                'description' => __('finance-minor-adjustment'),
                'sku' => 'adjustment',
                'price' => round($this->paymentData['subtotal'] - $itemsTotal, 2),
                'quantity' => 1,
                'amount' => round($this->paymentData['subtotal'] - $itemsTotal, 2),
            ];
        }
    }

    protected function createCustomerApiRequest()
    {
        $specificUrl = $this->getApiUrl('/v1/customers');

        $customerInfo = $this->prepareCustomerInfo();

        return $this->postToApi($specificUrl, $customerInfo);
    }

    public function registerWebhookRoutes(Router $router): void
    {
        $router->post('payment-webhook', function (Request $request) {
            if ($request->input('referenceUUID') && $request->input('status') === 'APPROVED') {
                $metadata = $request->input('metadata', []);
                $payableId = $metadata['payable_id'] ?? null;
                $payableType = $metadata['payable_type'] ?? null;

                if (!$payableId || !$payableType) {
                    Log::error('Missing payable information in webhook', ['metadata' => $metadata]);
                    return response()->json(['error' => 'Missing payable information'], 400);
                }

                $payable = app($payableType)::find($payableId);

                $paymentMethod = PaymentMethodEnum::from($metadata['payment_method_id']);

                PaymentProcessor::managePaymentResult(
                    PaymentResult::success(
                        transactionId: $request->input('referenceUUID'),
                        amount: $request->input('amount'),
                        paymentProviderCode: $this->getCode(),
                        metadata: $metadata,
                    ),
                    new PaymentContext(
                        payable: $payable,
                        paymentMethod: $paymentMethod,
                        metadata: $metadata,
                    )
                );
            }
        })->name('finance.webhooks.bna.payment');
    }

    protected function prepareCustomerInfo()
    {
        $completeName = $this->getDataFromRequest('complete_name') ?? $this->paymentContext->payable->getCustomerName() ?? 'Unknown Customer';
        $separatedName = explode(' ', $completeName, 2);

        $payable = $this->paymentContext->payable;

        $customerInfo = [
            'email' => $payable->getEmail() ?? 'unknown@gmail.com',
            'firstName' => $separatedName[0] ?? '',
            'lastName' => $separatedName[1] ?? '',
            'type' => 'Personal',
        ];

        if (!$payable->getAddress()) {
            abort(403, __('validation-customer-address-must-be-complete'));
        }

        $address = $payable->getAddress();

        $customerAddress = [];
        $customerAddress['postalCode'] = $address->postal_code;
        if ($address->street_number) $customerAddress['streetNumber'] = $address->street_number;
        if ($address->address1) $customerAddress['streetName'] = $this->parseAddressValue($address->address1 ?? '');
        if ($address->city) $customerAddress['city'] = $address->city;
        // $customerAddress['province'] = 'CA-ON2';
        if ($address->country) $customerAddress['country'] = $this->parseCountry($address->country ?? '');

        $customerInfo['address'] = $customerAddress;

        return $customerInfo;
    }

    protected function preparePaymentDetailsInfo()
    {
        $dateExpriy = $this->getDataFromRequest('expiration_date');
        $year = substr($dateExpriy, -2);
        $month = substr($dateExpriy, 0, strpos($dateExpriy, '/'));
        $month = $month < 10 ? '0'.$month : $month;

        $paymentDetailsInfo = [
            'cardNumber' => $this->getDataFromRequest('card_information'),
            'cardHolder' => $this->getDataFromRequest('complete_name'),
            'cardType' => 'credit',
            'cardIdNumber' => $this->getDataFromRequest('card_cvc'),
            'expiryMonth' => $month,
            'expiryYear' => $year,
        ];

        return $paymentDetailsInfo;
    }

    protected function prepareMetaDataInfo()
    {
        return $this->paymentContext->toProviderMetadata();
    }

    public function checkIfPaymentWasSuccessful()
    {
        return !$this->getDataFromResponse('errorCode') && ($this->getDataFromResponse('status') === 'APPROVED');
    }

    protected function getApiUrl($uri = '')
    {
        return $this->apiUrl.$uri;
    }

    protected function postToApi($specificUrl, $data)
    {
        return Http::withBasicAuth($this->accessKey, $this->secretKey)->post($specificUrl, $data);
    }

    protected function getDataFromRequest($key)
    {
        return $this->saleRequest[$key] ?? null;
    }

    protected function parseAddressValue($val)
    {
        $transliterator = Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', Transliterator::FORWARD);

        return str_replace('|', '', $transliterator->transliterate($val));
    }

    protected function parseCountry($country)
    {
        $mapping = [
            'Canada' => 'CA',
            'United States' => 'US',
        ];

        // Remove accents and special characters
        $transliterator = Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', Transliterator::FORWARD);
        $country = $transliterator->transliterate($country);

        return $mapping[$country] ?? $country;
    }

    protected function getDataFromResponse($key, $data = null)
    {
        $data = $data ?: $this->saleResponse;
        $keyArr = explode('.', $key, 2);

        if (count($keyArr) > 1) {
            if (!array_key_exists($keyArr[0], $data)) {
                Log::warning('ISSUE WITH MISSING key '.$keyArr[0]);
                Log::warning($data);
            }
            $data = $data[$keyArr[0]];
            return $this->getDataFromResponse($keyArr[1], $data);
        }

        return $data[$key] ?? null;
    }

}
