<?php

namespace Condoedge\Finance\Billing\Webhooks;

use Illuminate\Routing\Router;
use Illuminate\Http\Request;

trait RegistersWebhooks
{
    /**
     * Register webhook routes with standard middleware
     */
    public function registerWebhookRoutes(Router $router): void
    {
        $processor = $this->getWebhookProcessor();
        $providerCode = $this->getCode();

        $router->post("{$providerCode}/payment", function (Request $request) use ($processor) {
            return $processor->handle($request);
        })->name("finance.webhooks.{$providerCode}.payment")->middleware($this->getWebhookMiddleware());
    }
    
    /**
     * Get webhook processor instance
     */
    abstract protected function getWebhookProcessor(): WebhookProcessor;
    
    /**
     * Get middleware for webhook routes
     */
    protected function getWebhookMiddleware(): array
    {
        return [
            'throttle:20,1', // 20 requests per minute
            // Add any other middleware here
        ];
    }
}
