<?php
namespace Condoedge\Finance\Billing;

use Illuminate\Http\Client\Request;
use Illuminate\Routing\Router;
use Kompo\Elements\BaseElement;

interface PaymentGatewayInterface
{
    /**
     * Provider identifier
     */
    public function getCode(): string;

    /**
     * Initialize payment
     */
    // public function initializePayment(PaymentContext $context): PaymentInitResponse;

    /**
     * Execute payment
     *
     * @param PaymentContext $context
     * @return mixed
     */
    public function processPayment(PaymentContext $context): PaymentResult;

    public function getPaymentForm(PaymentContext $context): ?BaseElement;
    
    
    /**
     * Get supported payment methods
     */
    public function getSupportedPaymentMethods(): array;


    // WEBHOOKS MANAGMENT
    /**
     * Register webhook routes
     */
    public function registerWebhookRoutes(Router $router): void;
}
