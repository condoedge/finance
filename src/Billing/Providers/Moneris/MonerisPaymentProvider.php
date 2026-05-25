<?php

namespace Condoedge\Finance\Billing\Providers\Moneris;

use Condoedge\Finance\Billing\Contracts\PaymentGatewayInterface;
use Condoedge\Finance\Billing\Core\ErrorClassification;
use Condoedge\Finance\Billing\Core\PaymentActionEnum;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentFlowEnum;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Condoedge\Finance\Billing\Core\WebhookProcessor;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\ProviderCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Kompo\Elements\BaseElement;

/**
 * Moneris Hosted Checkout (MCO) provider.
 *
 * Flow (per reference moneris-checkout-webapp-with-server.js):
 *  1. User clicks "Pay with Moneris" in InvoicePayModal.
 *  2. processPayment() with no ticket → POST /chktv2/request/request.php?action=preload
 *     → Moneris returns a ticket → we return PaymentResult::pending() with action
 *     = REDIRECT and redirectUrl = gateway.moneris.com/chkt/index.php?ticket=...
 *  3. The Kompo action handler redirects the browser there.
 *  4. User pays on Moneris's hosted page (PCI scope sits with Moneris).
 *  5. Moneris redirects user back to our return URL with the same ticket.
 *  6. MonerisReturnController rebuilds PaymentContext with paymentData=['ticket'=>...]
 *     and calls processPayment() again → this time the ticket is present, so we
 *     POST action=receipt → Moneris returns the txn outcome → success/fail PaymentResult.
 *
 * Reuses existing PaymentActionEnum::REDIRECT — no new infrastructure needed.
 */
class MonerisPaymentProvider implements PaymentGatewayInterface
{
    use \Condoedge\Finance\Billing\Traits\RegistersWebhooks;
    use \Condoedge\Finance\Billing\Traits\BasicGatewayTrait;

    protected string $host;
    protected string $storeId;
    protected string $apiToken;
    protected string $checkoutId;
    protected bool $isTest;
    protected ?ProviderCredentials $credentials = null;

    // Moneris MCO receipt key for the convenience-fee amount. Verify against a real receipt response.
    private const RECEIPT_CONVENIENCE_FEE_KEY = 'fee_amount';

    public function __construct()
    {
        $this->host = config('kompo-finance.services.moneris.host', 'gateway.moneris.com');
        $this->storeId = (string) config('kompo-finance.services.moneris.store_id', '');
        $this->apiToken = (string) config('kompo-finance.services.moneris.api_token', '');
        $this->checkoutId = (string) config('kompo-finance.services.moneris.checkout_id', '');
        $this->isTest = (bool) config('kompo-finance.services.moneris.is_test', true);
    }

    public function getCode(): string
    {
        return 'moneris';
    }

    public function getDisplayName(): string
    {
        return 'Moneris';
    }

    public function getCheckoutFlow(): PaymentFlowEnum
    {
        return PaymentFlowEnum::HOSTED_REDIRECT;
    }

    public function getSupportedPaymentMethods(): array
    {
        // Moneris Checkout (MCO) hosted page handles credit cards and Interac
        // Online. It does NOT do EFT/bank transfer, so BANK_TRANSFER is excluded.
        // Note: Interac via Moneris only surfaces to customers if Moneris is a
        // team's primary provider, or offer_fallback_provider_methods is on.
        return [
            PaymentMethodEnum::CREDIT_CARD,
            PaymentMethodEnum::INTERAC,
        ];
    }

    public function getPaymentForm(PaymentContext $context): ?BaseElement
    {
        // Hosted flow — no inline form. InvoicePayModal renders only a "Pay with
        // Moneris" submit button; submitting fires the existing payInvoice flow
        // which lands in processPayment() below.
        return null;
    }

    public function withCredentials(?ProviderCredentials $creds): static
    {
        if (!$creds) {
            return $this;
        }

        $copy = clone $this;
        $copy->credentials = $creds;

        if ($host = $creds->get('host')) {
            $copy->host = $host;
        }
        if ($storeId = $creds->get('store_id')) {
            $copy->storeId = (string) $storeId;
        }
        if ($token = $creds->get('api_token')) {
            $copy->apiToken = (string) $token;
        }
        if ($checkoutId = $creds->get('checkout_id')) {
            $copy->checkoutId = (string) $checkoutId;
        }
        $copy->isTest = (bool) ($creds->get('is_test') ?? $copy->isTest);

        return $copy;
    }

    /**
     * Single entry point. Ticket presence in paymentData decides the leg:
     *   - missing: initiate hosted checkout (preload + redirect)
     *   - present: confirm hosted checkout (receipt lookup)
     */
    public function processPayment(PaymentContext $context): PaymentResult
    {
        $ticket = $context->paymentData['ticket'] ?? null;

        return $ticket
            ? $this->confirmReceipt($context, $ticket)
            : $this->initiateCheckout($context);
    }

    /**
     * Leg 1: ask Moneris for a checkout ticket, return a REDIRECT pending result.
     */
    private function initiateCheckout(PaymentContext $context): PaymentResult
    {
        $client = $this->client();
        $orderNo = $this->orderNumberFor($context);
        $amount = number_format($context->payable->getPayableAmount()->toFloat(), 2, '.', '');

        $response = $client->preload([
            'order_no' => $orderNo,
            'cust_id' => (string) ($context->getTeamId() ?? 'team-anon'),
            'txn_total' => $amount,
            'language' => app()->getLocale() === 'fr' ? 'fr-ca' : 'en-ca',
        ]);

        $ticket = $response['ticket'] ?? null;
        if (!$ticket) {
            $code = $response['response_code'] ?? null;
            throw new \RuntimeException(
                "Moneris preload failed (code={$code}): " . json_encode($response),
            );
        }

        $redirectUrl = "https://{$this->host}/chkt/index.php?ticket=" . urlencode($ticket);

        return PaymentResult::pending(
            transactionId: $ticket,
            amount: $context->payable->getPayableAmount()->toFloat(),
            paymentProviderCode: $this->getCode(),
            metadata: ['order_no' => $orderNo, 'is_test' => $this->isTest],
            action: PaymentActionEnum::REDIRECT,
            redirectUrl: $redirectUrl,
        );
    }

    /**
     * Leg 2: user returned from Moneris with a ticket. Confirm via /receipt.
     */
    private function confirmReceipt(PaymentContext $context, string $ticket): PaymentResult
    {
        try {
            $receipt = $this->client()->receipt($ticket);
        } catch (\Throwable $e) {
            Log::error('Moneris receipt lookup failed', [
                'ticket' => $ticket,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $code = $receipt['response_code'] ?? null;
        $txnNumber = $receipt['txn_number'] ?? $ticket;
        $amount = isset($receipt['amount']) ? (float) $receipt['amount'] : 0.0;
        $message = $receipt['message'] ?? null;

        if (MonerisResponseCodeMap::isApproved($code)) {
            return PaymentResult::success(
                transactionId: (string) $txnNumber,
                amount: $amount,
                paymentProviderCode: $this->getCode(),
                metadata: [
                    'response_code' => $code,
                    'iso' => $receipt['iso_code'] ?? null,
                    'reference' => $receipt['reference_num'] ?? null,
                ],
                processorFees: $this->extractConvenienceFee($receipt),
            );
        }

        return PaymentResult::failed(
            errorMessage: $message ?? __('finance-payment-failed'),
            transactionId: (string) $txnNumber,
            paymentProviderCode: $this->getCode(),
        );
    }

    /**
     * Convenience-fee amount Moneris charged on this transaction, when present.
     */
    private function extractConvenienceFee(array $receipt): ?float
    {
        $fee = $receipt[self::RECEIPT_CONVENIENCE_FEE_KEY] ?? null;

        return is_numeric($fee) ? (float) $fee : null;
    }

    public function classifyError(\Throwable $e): ErrorClassification
    {
        if ($e instanceof ConnectionException) {
            return ErrorClassification::network('moneris_network', $e->getMessage());
        }

        // preload/receipt embed Moneris response codes in their RuntimeException message.
        if (preg_match('/code=([A-Za-z0-9_-]+)/', $e->getMessage(), $m)) {
            return MonerisResponseCodeMap::classify($m[1], $e->getMessage());
        }

        return ErrorClassification::transient('moneris_api', $e->getMessage());
    }

    protected function getWebhookProcessor(): WebhookProcessor
    {
        return new MonerisWebhookProcessor($this->apiToken);
    }

    private function client(): MonerisClient
    {
        return new MonerisClient(
            host: $this->host,
            storeId: $this->storeId,
            apiToken: $this->apiToken,
            checkoutId: $this->checkoutId,
        );
    }

    /**
     * Build a globally-unique order_no. Moneris rejects duplicates within a
     * store, so we prefix with payable type+id and add a timestamp suffix.
     */
    private function orderNumberFor(PaymentContext $context): string
    {
        $payable = $context->payable;
        $base = $payable->getPayableType() . '-' . $payable->getPayableId();
        return substr($base . '-' . time() . '-' . substr(bin2hex(random_bytes(3)), 0, 6), 0, 50);
    }
}
