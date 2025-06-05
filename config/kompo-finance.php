<?php

use Condoedge\Finance\Billing\TempPaymentGateway;
use Condoedge\Finance\Models\CustomableTeam;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\GlobalScopesTypes\Credit;

return [
    'decimal-scale' => 5,

    'automatic-handle-of-unmanaged-decimals' => !env('SAFE_DECIMAL_DISABLE_HANDLER', env('APP_ENV') != 'production'),

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
            \Condoedge\Finance\Models\InvoiceDetailTax::class,
            \Condoedge\Finance\Models\InvoiceApply::class,
        ],
        \Condoedge\Finance\Models\InvoiceDetailTax::class => [
           \Condoedge\Finance\Models\InvoiceDetail::class,
        ],

        \Condoedge\Finance\Models\CustomerPayment::class => [
            \Condoedge\Finance\Models\InvoiceApply::class,
        ]
    ],

    'invoice_applicable_types' => [
        \Condoedge\Finance\Models\GlobalScopesTypes\Credit::class, // CREDIT TYPE
        \Condoedge\Finance\Models\CustomerPayment::class, // CUSTOMER PAYMENT TYPE
    ],

    'customable_models' => [
        'customable_team' => CustomableTeam::class,
    ],

    'payment_gateways' => [
        \Condoedge\Finance\Models\PaymentTypeEnum::CASH->value => TempPaymentGateway::class,
    ],

    // These are used to bind "config-currency" to the locale in our service provider
    // But as default we use the logic behind this. If we don't set "config-currency" in the service provider, it will use the default config
    'currency_preformats' => [
        'en' => [
            'format' => '#,###.00# $',
        ],
        'fr' => [
            'format' => '#.###,00### $',
        ],
    ],

    // This is not used, just to show all the settings possibles in the config file
    'currency' => [
        /*
        |--------------------------------------------------------------------------
        | Format
        |--------------------------------------------------------------------------
        |
        | If you set 'format', it overrides the following default currency properties:
        |   - 'symbol'
        |   - 'position'
        |   - 'decimal_separator'
        |   - 'thousands_separator'
        |   - 'min_number_of_decimals'
        |   - 'max_number_of_decimals'
        |
        | Example:
        | 'format' => '$ #,###.00###',
        */
        'format' => '$ #,###.00###',

        'symbol' => '$',
        'position' => 'left',
        'decimal_separator' => '.',
        'thousands_separator' => ',',
        'min_number_of_decimals' => 2,
        'max_number_of_decimals' => 3,

        /*
        |--------------------------------------------------------------------------
        | Rounding Mode
        |--------------------------------------------------------------------------
        |
        | Define how decimal values should be handled when they exceed the maximum number of decimals
        | Options:
        | 'round' - Standard mathematical rounding (e.g., 3.456 with 2 decimals becomes 3.46)
        | 'truncate' - Simply cuts off excess decimals (e.g., 3.456 with 2 decimals becomes 3.45)
        | 'ceiling' - Always rounds up (e.g., 3.451 with 2 decimals becomes 3.46)
        | 'floor' - Always rounds down (e.g., 3.459 with 2 decimals becomes 3.45)
        */
        'rounding_mode' => 'round',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Currency Formatter
    |--------------------------------------------------------------------------
    |
    | Define a callable that takes a numeric value and returns a formatted string
    | This allows developers to completely override the default formatting logic
    | Example: 'formatter' => function($value) { return '$' . number_format($value, 2); }
    |
    | Leave this as null to use the default formatter
    */
    'custom_currency_formatter' => null,

    CUSTOMER_PAYMENT_MODEL_KEY . '-namespace' => getAppClass(App\Models\CustomerPayment::class, \Condoedge\Finance\Models\CustomerPayment::class),

    CUSTOMER_MODEL_KEY . '-namespace' => getAppClass(App\Models\Customer::class, Condoedge\Finance\Models\Customer::class),

    INVOICE_MODEL_KEY . '-namespace' => getAppClass(App\Models\Invoice::class, Condoedge\Finance\Models\Invoice::class),

    INVOICE_DETAIL_MODEL_KEY . '-namespace' => getAppClass(App\Models\InvoiceDetail::class, Condoedge\Finance\Models\InvoiceDetail::class),

    INVOICE_PAYMENT_MODEL_KEY . '-namespace' => getAppClass(App\Models\InvoicePayment::class, Condoedge\Finance\Models\InvoiceApply::class),

    TAX_MODEL_KEY . '-namespace' => getAppClass(App\Models\Tax::class, Condoedge\Finance\Models\Tax::class),

    TAX_GROUP_MODEL_KEY . '-namespace' => getAppClass(App\Models\TaxGroup::class, Condoedge\Finance\Models\TaxGroup::class),

    PAYMENT_TYPE_ENUM_KEY . '-namespace' => \Condoedge\Finance\Models\PaymentTypeEnum::class,

    INVOICE_TYPE_ENUM_KEY . '-namespace' => \Condoedge\Finance\Models\InvoiceTypeEnum::class,
    
];
