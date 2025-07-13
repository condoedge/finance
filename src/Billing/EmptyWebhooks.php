<?php

namespace Condoedge\Finance\Billing;

use Illuminate\Http\Client\Request;
use Illuminate\Routing\Router;

trait EmptyWebhooks
{
    /**
     * Register webhook routes
     */
    public function registerWebhookRoutes(Router $router): void
    {
        // No webhooks to register
    }
}