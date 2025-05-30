<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class VendorPaymentModel extends Facade
{
    protected static function getFacadeAccessor()
    {
        return VENDOR_PAYMENT_MODEL_KEY;
    }
}
