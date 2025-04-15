<?php

use Condoedge\Finance\Models\PaymentTypeEnum;
use Condoedge\Finance\Models\TaxGroup;

return [
    'default_tax_group_id' => TaxGroup::latest()->first()?->id,
    'default_payment_type_id' => PaymentTypeEnum::CASH->value,
];