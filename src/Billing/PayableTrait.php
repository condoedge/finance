<?php
namespace Condoedge\Finance\Billing;

use Condoedge\Utils\Models\ContactInfo\Maps\Address;

trait PayableTrait
{
    public function getPayableId(): int
    {
        return $this->id;
    }
    
    public function getPayableType(): string
    {
        return $this->getMorphClass();
    }

    public function getAddress(): ?Address
    {
        return $this->address ?? null;
    }

    public function getEmail(): ?string
    {
        if ($this instanceof FinancialPayableInterface) {
            return $this->getCustomer()?->email ?? null;
        }

        return '';
    }

    public function getPaymentMetadata(): array 
    {
        return [
            'payable_id' => $this->getPayableId(),
            'payable_type' => $this->getPayableType(),
            'team_id' => $this->getTeamId(),
            'customer_id' => $this->getCustomer()?->id ?? null,
        ];
    }
}