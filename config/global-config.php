<?php

use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Models\TaxGroup;
use Illuminate\Support\Facades\Schema;

return [
    'default_tax_group_id' => Schema::hasTable('fin_taxes_groups') ? TaxGroup::latest()->first()?->id : null,
    'default_payment_method_id' => PaymentMethodEnum::CASH->value,
];
