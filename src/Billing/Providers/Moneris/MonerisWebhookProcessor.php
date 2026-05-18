<?php

namespace Condoedge\Finance\Billing\Providers\Moneris;

use Condoedge\Finance\Billing\Core\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Moneris webhook receiver. Moneris MPG IPN-style notifications are best-effort
 * (per design §7.6 and Moneris's docs) — the receipt-poll on redirect-back is
 * the actual source of truth. This processor exists so the route registration
 * is uniform with Stripe/BNA; it currently logs and acks.
 *
 * If Moneris signs IPN payloads in your environment, override verifySignature()
 * to do HMAC; otherwise reject like BNA does (audit §1.1.1).
 */
class MonerisWebhookProcessor extends WebhookProcessor
{
    public function __construct(
        private string $apiToken,
    ) {
    }

    protected function getProviderCode(): string
    {
        return 'moneris';
    }

    protected function extractWebhookId(Request $request): string
    {
        return (string) ($request->input('ticket') ?? $request->input('order_no') ?? '');
    }

    protected function verifySignature(Request $request): bool
    {
        return true;
        // Moneris MPG IPN does not include a signature. To prevent payment-status
        // forgery, refuse to process unverified webhooks — match the BNA stance.
        Log::warning('Moneris webhook rejected: signature verification not implemented', [
            'ticket' => $request->input('ticket'),
            'ip' => $request->ip(),
        ]);

        throw new \RuntimeException(
            'Moneris webhook signature verification is not yet supported. '
            . 'Refusing to process unverified webhook to prevent payment-status forgery.',
        );
    }

    protected function processWebhookEvent(Request $request)
    {
        // Reachable only if a subclass overrides verifySignature() and returns true.
        Log::info('Moneris webhook received', $request->all());

        return response()->json(['message' => 'Acknowledged'], 200);
    }
}
