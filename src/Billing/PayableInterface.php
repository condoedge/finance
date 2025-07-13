<?php
namespace Condoedge\Finance\Billing;

use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Illuminate\Support\Collection;

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

    public function getAddress(): ?Address;
    public function getEmail(): ?string;

    public function getCustomerName(): ?string;
}