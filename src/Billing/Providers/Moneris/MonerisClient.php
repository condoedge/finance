<?php

namespace Condoedge\Finance\Billing\Providers\Moneris;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP wrapper over the Moneris Checkout (MPG hosted) JSON API.
 *
 * The reference implementation in `MonerisPayment/App_Code/PaiementGP.cs` and
 * `App_Code/TransactionPaiement.cs` shows the wire shape — we lift the request
 * field names and response code map (see MonerisResponseCodeMap) but not the
 * C# code itself. The endpoint is a single URL with an `action` field selecting
 * `preload` (issue ticket) or `receipt` (look up txn after redirect-back).
 *
 * Host:
 *   test: esqa.moneris.com
 *   prod: www3.moneris.com
 *
 * Both call /chkt/request/request.php.
 */
class MonerisClient
{
    public function __construct(
        private string $host,
        private string $storeId,
        private string $apiToken,
        private string $checkoutId,
        private int $timeoutSeconds = 30,
    ) {
    }

    /**
     * Step 1 of hosted checkout: register the order and receive a ticket. The
     * client then POSTs the ticket to Moneris's hosted page; the user enters
     * card info there.
     *
     * @return array{ticket?: string, response_code?: string, error?: string}
     */
    public function preload(array $orderData): array
    {
        return $this->request('preload', $orderData);
    }

    /**
     * Step 3 of hosted checkout: after the user is redirected back to us with
     * a ticket, we ask Moneris for the receipt to confirm the txn. Moneris
     * webhooks/IPN are unreliable per their docs — receipt-poll is the source
     * of truth (see design §7.6).
     *
     * @return array Receipt payload (response_code, txn_number, amount, etc.)
     */
    public function receipt(string $ticket): array
    {
        return $this->request('receipt', ['ticket' => $ticket]);
    }

    /**
     * @throws \RuntimeException on network failure (caller catches via the
     *         provider's classifyError(); ConnectionException becomes a
     *         NETWORK ErrorClassification, others TRANSIENT.)
     */
    private function request(string $action, array $data): array
    {
        $payload = array_merge([
            'store_id' => $this->storeId,
            'api_token' => $this->apiToken,
            'checkout_id' => $this->checkoutId,
            'environment' => 'qa', // ignored by prod; harmless
            'action' => $action,
        ], $data);

        $url = "https://{$this->host}/chktv2/request/request.php";

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->asJson()
                ->acceptJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            // Bubble up unchanged — provider classifyError() maps to NETWORK.
            throw $e;
        }

        if (!$response->successful()) {
            throw new \RuntimeException(
                "Moneris API HTTP {$response->status()}: " . $response->body(),
            );
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new \RuntimeException('Moneris API returned non-JSON response');
        }

        // Moneris wraps results in a 'response' envelope. Flatten for callers.
        return $body['response'] ?? $body;
    }

}
