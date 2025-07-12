<?php

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\CustomerPayment;
use Illuminate\Database\Eloquent\Collection;

interface PayableInterface {
    public function getPayableId(): int;
    public function getPayableType(): string;
    public function getTeamId(): int;
    public function getPayableAmount(): SafeDecimal;

    /**
     * @return Collection<PayableLineDto>
     */
    public function getPayableLines(): Collection;
    public function getPaymentDescription(): string;


    public function getPaymentMetadata(): array;
}

interface FinantialPayableInterface extends PayableInterface
{
    public function getCustomer(): ?Customer;
    public function onPaymentSuccess(CustomerPayment $payment): void;
    public function onPaymentFailed(array $failureData): void;
}