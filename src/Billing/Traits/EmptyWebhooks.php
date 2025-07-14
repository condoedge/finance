<?php

namespace Condoedge\Finance\Billing\Traits;

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
