<?php

namespace Condoedge\Finance\Billing;

use Illuminate\Http\Client\Request;
use Illuminate\Routing\Router;

trait UseEmptyWebhooks
{
    /**
     * Register webhook routes
     */
    public function registerWebhookRoutes(Router $router): void
    {

    }
    
    /**
     * Process webhook - returns payment data if successful
     */
    public function processWebhook(Request $request): WebhookResult
    {
        throw new \Exception('Webhook processing is not implemented in this payment provider.');
    }
    
    /**
     * Verify webhook authenticity
     */
    public function verifyWebhook(Request $request): bool
    {
        throw new \Exception('Webhook verification is not implemented in this payment provider.');
    }
}