<?php

namespace Condoedge\Finance\Models;

use Kompo\Auth\Models\Maps\Address;

class CustomerAddress extends Address
{
    protected $table = 'fin_customer_addresses';

    public function setTeamId($teamId = null)
    {
        return null;
    }
}