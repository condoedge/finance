<?php

namespace Condoedge\Finance\Facades;

use Illuminate\Support\Facades\Facade;

class BillDetailModel extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BILL_DETAIL_MODEL_KEY;
    }
}
