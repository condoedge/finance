<?php

use Condoedge\Finance\Models\PaymentTypeEnum;
use Condoedge\Finance\Models\TaxGroup;
use Illuminate\Support\Facades\Schema;

return [
    'default_tax_group_id' => Schema::hasTable('fin_taxes_groups') ? TaxGroup::latest()->first()?->id : null,
    'default_payment_type_id' => PaymentTypeEnum::CASH->value,
];