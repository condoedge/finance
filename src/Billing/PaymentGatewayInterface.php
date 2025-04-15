<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Models\Account;

interface PaymentGatewayInterface
{
    public function getCashAccount(): Account;

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