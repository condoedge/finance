<?php

namespace Condoedge\Finance\Events;

use Condoedge\Finance\Models\Payable\Bill;

class BillGenerated
{
    use \Illuminate\Foundation\Events\Dispatchable;

    public function __construct(
        public Bill $bill
    ) {}
}
