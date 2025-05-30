<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class BillModel extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BILL_MODEL_KEY;
    }
}
