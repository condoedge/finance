<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class BillPaymentModel extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BILL_PAYMENT_MODEL_KEY;
    }
}
