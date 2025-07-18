<?php

use Condoedge\Finance\Billing\Providers\Bna\BnaPaymentProvider;
use Condoedge\Finance\Billing\Providers\Stripe\StripePaymentProvider;
use Condoedge\Finance\Models\CustomableTeam;
use Condoedge\Finance\Models\PaymentMethodEnum;

return [
    'payment_providers' => [
        BnaPaymentProvider::class,
        StripePaymentProvider::class,
    ],

    'payment_method_providers' => [
        PaymentMethodEnum::CREDIT_CARD->value => StripePaymentProvider::class,
        PaymentMethodEnum::INTERAC->value => BnaPaymentProvider::class,
        PaymentMethodEnum::BANK_TRANSFER->value => StripePaymentProvider::class,
    ],

    'services' => [
        'bna_payment_provider' => [
            'api_url' => env('BNA_PAYMENT_API_URL', 'https://stage-api-service.bnasmartpayment.com'),
            'api_key' => env('BNA_PAYMENT_API_KEY', ''),
            'api_secret' => env('BNA_PAYMENT_API_SECRET', ''),
        ],
        'stripe' => [
            'api_key' => env('STRIPE_KEY', ''),
            'secret_key' => env('STRIPE_SECRET', ''),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        ],
    ],

    'decimal-scale' => 5,

    /**
     * This configuration is used to define the decimal scale for payment-related calculations.
     * It is set to 2 by default, which is suitable for most financial transactions.
     * This means that payment amounts will be rounded to two decimal places.
     *
     * See https://github.com/condoedge/SISC/discussions/694
     */
    'payment-related-decimal-scale' => 5,

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
            // \Condoedge\Finance\Models\GlTransactionHeader::class, // GL transactions linked to customers
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
        ],
        \Condoedge\Finance\Models\PaymentInstallmentPeriod::class => [
            \Condoedge\Finance\Models\Invoice::class,
        ],
        \Condoedge\Finance\Models\GlAccount::class => [
            \Condoedge\Finance\Models\AccountSegmentAssignment::class,
            \Condoedge\Finance\Models\AccountSegment::class,
            \Condoedge\Finance\Models\SegmentValue::class,
        ],
        // // GL Module relationships
        \Condoedge\Finance\Models\GlTransactionHeader::class => [
            \Condoedge\Finance\Models\GlTransactionLine::class,
        ],
        \Condoedge\Finance\Models\Product::class => [
        ],

        \Condoedge\Finance\Models\ExpenseReport::class => [
            \Condoedge\Finance\Models\Expense::class,
        ],
    ],

    'invoice_applicable_types' => [
        \Condoedge\Finance\Models\GlobalScopesTypes\Credit::class, // CREDIT TYPE
        \Condoedge\Finance\Models\CustomerPayment::class, // CUSTOMER PAYMENT TYPE
    ],

    'customable_models' => [
        'customable_team' => CustomableTeam::class,
    ],

    // These are used to bind "config-currency" to the locale in our service provider
    // But as default we use the logic behind this. If we don't set "config-currency" in the service provider, it will use the default config
    'currency_preformats' => [
        'en' => [
            'format' => '#,###.00 $',
        ],
        'fr' => [
            'format' => '#.###,00 $',
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

    PAYMENT_METHOD_ENUM_KEY . '-namespace' => \Condoedge\Finance\Models\PaymentMethodEnum::class,

    INVOICE_TYPE_ENUM_KEY . '-namespace' => \Condoedge\Finance\Models\InvoiceTypeEnum::class,

    PRODUCT_TYPE_ENUM_KEY . '-namespace' => \Condoedge\Finance\Models\ProductTypeEnum::class,

    PRODUCT_MODEL_KEY . '-namespace' => \Condoedge\Finance\Models\Product::class,

    SEGMENT_DEFAULT_HANDLER_ENUM_KEY . '-namespace' => \Condoedge\Finance\Enums\SegmentDefaultHandlerEnum::class,
];
