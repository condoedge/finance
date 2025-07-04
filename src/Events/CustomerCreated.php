<?php

namespace Condoedge\Finance\Events;

class CustomerCreated
{
    protected $customer;

    public function __construct($customer)
    {
        $this->customer = $customer;
    }

    public function getCustomer()
    {
        return $this->customer;
    }
}
