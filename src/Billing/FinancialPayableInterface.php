<?php

namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\HistoricalCustomer;

interface FinancialPayableInterface extends PayableInterface
{

    /**
     * @return Customer|HistoricalCustomer|null
     */
    public function getCustomer(): mixed;
    public function onPaymentSuccess(CustomerPayment $payment): void;
    public function onPaymentFailed(array $failureData): void;
}