<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Models\GlAccount;

interface PaymentGatewayInterface
{
    /**
     * Get the cash account for this payment gateway
     */
    public function getCashAccount(): GlAccount;

    /**
     * Optional: Initialize gateway with context
     *
     * This method is called by PaymentGatewayResolver::resolveWithContext()
     * to provide additional context to the gateway
     */
    public function initializeContext(array $context = []): void;

    // COUPONS


    // CHECKOUT SESSIONS


    // DISPUTES

    // REFUNDS
    public function refundOrder();

    // BANK ACCOUNTS

    // SUBSCRIPTIONS

    /* WEBHOOKS */
    public function setRoutes();
}
