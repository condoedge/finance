<?php

namespace Condoedge\Finance\Billing;

use Illuminate\Support\Facades\Http;
use Transliterator;

class BnaPaymentProvider extends AbstractPaymentProvider
{
    protected $apiUrl;

    protected $accessKey;
    protected $secretKey;
    protected $credentials;

    protected $saleRequest;
    protected $paymentData;
    protected $saleResponse;

    public function __construct()
    {
        $this->apiUrl = config('kompo-finance.services.bna_payment_provider.api_url', 'https://stage-api-service.bnasmartpayment.com');

        $this->accessKey = config('kompo-finance.services.bna_payment_provider.api_key', '');
        $this->secretKey = config('kompo-finance.services.bna_payment_provider.api_secret', '');

        $this->credentials = base64_encode($this->accessKey.': '.$this->secretKey);
    }

    public function createSale($request, $onSuccess = null)
    {
        $this->ensureInvoiceIsSet();

        $this->saleRequest = $request;

        $response = $this->createPaymentApiRequest();

        $this->saleResponse = json_decode($response->body(), true);
    }

    protected function createPaymentApiRequest()
    {
        return $this->createSaleApiRequest();
    }

    protected function createSaleApiRequest()
    {
        $specificUrl = $this->getApiUrl('/v1/transaction/card/sale');

        $this->paymentData = [
            'transactionTime' => carbon(now())->format('c'),
            'applyFee' => false,
            'currency' => 'CAD',
        ];

        $this->paymentData['customerInfo'] = $this->prepareCustomerInfo();

        foreach ($this->getPayableLines() as $od) {
            $finalItems[] = $od->toArray();
        }
        $this->paymentData['items'] = $finalItems;
        $this->paymentData['subtotal'] = collect($finalItems)->sum(fn ($i) => $i['amount']);

        $this->paymentData['paymentDetails'] = $this->preparePaymentDetailsInfo();

        $this->paymentData['metadata'] = $this->prepareMetaDataInfo();

        \Log::warning('paymentData', $this->paymentData);

        return $this->postToApi($specificUrl, $this->paymentData);
    }

    protected function createCustomerApiRequest()
    {
        $specificUrl = $this->getApiUrl('/v1/customers');

        $customerInfo = $this->prepareCustomerInfo();

        return $this->postToApi($specificUrl, $customerInfo);
    }

    protected function prepareCustomerInfo()
    {
        $completeName = $this->getDataFromRequest('complete_name');
        $separatedName = explode(' ', $completeName, 2);

        $customerInfo = [
            'email' => $this->invoice->customer->email,
            'firstName' => $separatedName[0] ?? '',
            'lastName' => $separatedName[1] ?? '',
        ];

        if ($this->invoice->customer->address) {
            $customerAddress['postalCode'] = $this->invoice->customer->address->postal_code;
            $customerAddress['streetNumber'] = $this->invoice->customer->address->street_number ?? '';
            $customerAddress['streetName'] = $this->invoice->customer->address->address ?? '';
            $customerAddress['city'] = $this->invoice->customer->address->city ?? '';
            $customerAddress['province'] = $this->invoice->customer->address->state ?? '';
            $customerAddress['country'] = $this->invoice->customer->address->country ?? '';

            $customerInfo['address'] = $customerAddress;
        }

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
        $metaDataInfo = [
            'invoice_id' => $this->invoice->id,
        ];

        return $metaDataInfo;
    }

    public function checkIfPaymentWasSuccessful()
    {
        dd($this->getDataFromResponse('errorCode'));
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

        return $transliterator->transliterate($val);
    }

    protected function getDataFromResponse($key, $data = null)
    {
        $data = $data ?: $this->saleResponse;
        $keyArr = explode('.', $key, 2);

        if (count($keyArr) > 1) {
            if (!array_key_exists($keyArr[0], $data)) {
                \Log::warning('ISSUE WITH MISSING key '.$keyArr[0]);
                \Log::warning($data);
            }
            $data = $data[$keyArr[0]];
            return $this->getDataFromResponse($keyArr[1], $data);
        }

        return $data[$key] ?? null;
    }

}
