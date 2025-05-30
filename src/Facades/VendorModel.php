<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class VendorModel extends Facade
{
    protected static function getFacadeAccessor()
    {
        return VENDOR_MODEL_KEY;
    }
}
