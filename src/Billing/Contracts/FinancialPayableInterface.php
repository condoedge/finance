<?php

namespace Condoedge\Finance\Billing\Contracts;

use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\CustomerPayment;

interface FinancialPayableInterface extends PayableInterface
{
    public function getCustomer(): ?Customer;
    public function onPaymentSuccess(CustomerPayment $payment): void;
    public function onPaymentFailed(array $failureData): void;
}
