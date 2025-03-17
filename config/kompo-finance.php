<?php

use Condoedge\Finance\Models\CustomableTeam;
use Kompo\Auth\Models\Teams\Team;

return [
    CUSTOMER_MODEL_KEY . '-namespace' => getAppClass(App\Models\Customer::class, Condoedge\Finance\Models\Customer::class),

    CUSTOMER_ADDRESS_MODEL_KEY . '-namespace' => getAppClass(App\Models\CustomerAddress::class, Condoedge\Finance\Models\CustomerAddress::class),

    INVOICE_MODEL_KEY . '-namespace' => getAppClass(App\Models\Invoice::class, Condoedge\Finance\Models\Invoice::class),

    INVOICE_DETAIL_MODEL_KEY . '-namespace' => getAppClass(App\Models\InvoiceDetail::class, Condoedge\Finance\Models\InvoiceDetail::class),

    INVOICE_PAYMENT_MODEL_KEY . '-namespace' => getAppClass(App\Models\InvoicePayment::class, Condoedge\Finance\Models\InvoicePayment::class),

    TAX_MODEL_KEY . '-namespace' => getAppClass(App\Models\Tax::class, Condoedge\Finance\Models\Tax::class),

    TAX_GROUP_MODEL_KEY . '-namespace' => getAppClass(App\Models\TaxGroup::class, Condoedge\Finance\Models\TaxGroup::class),

    PAYMENT_TYPE_ENUM_KEY . '-namespace' => \Condoedge\Finance\Models\PaymentTypeEnum::class,

    INVOICE_TYPE_ENUM_KEY . '-namespace' => \Condoedge\Finance\Models\InvoiceTypeEnum::class,
    
    /*
    |--------------------------------------------------------------------------
    | Configuration for integrity verification
    |--------------------------------------------------------------------------
    |
    | This configuration defines the relationships between models for integrity verification.
    | The keys are parent model classes and the values are arrays of child model classes.
    |
    */
    'model_integrity_relations' => [
        \Condoedge\Finance\Models\Customer::class => [
            \Condoedge\Finance\Models\Invoice::class,
            \Condoedge\Finance\Models\CustomerPayment::class,
        ],
        \Condoedge\Finance\Models\Invoice::class => [
            \Condoedge\Finance\Models\InvoiceDetail::class,
            \Condoedge\Finance\Models\InvoicePayment::class,
        ],
    ],

    'customable_models' => [
        CustomableTeam::class,
    ],
];
