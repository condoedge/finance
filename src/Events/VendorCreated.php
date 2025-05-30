<?php

namespace Condoedge\Finance\Events;

use Condoedge\Finance\Models\Payable\Vendor;

class VendorCreated
{
    use \Illuminate\Foundation\Events\Dispatchable;

    public function __construct(
        public Vendor $vendor
    ) {}
}
