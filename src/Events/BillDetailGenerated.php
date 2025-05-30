<?php

namespace Condoedge\Finance\Events;

use Condoedge\Finance\Models\Payable\BillDetail;

class BillDetailGenerated
{
    use \Illuminate\Foundation\Events\Dispatchable;

    public function __construct(
        public BillDetail $billDetail
    ) {}
}
