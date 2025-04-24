# condoedge/finance

## Package explanation

This package is designed to provide a comprehensive solution for managing financial transactions and products. The idea is ensuring the integrity of the data and providing some components for managing financial transactions. Including paymentGateways, accounts, some payments methods (extendable), invoices, accouting, taxes, and more.

## Installation

### Install the package using composer:

```cmd
composer require condoedge/finance
```

### Run migrations

To create the necessary tables in your database, run the following command:

```cmd
php artisan migrate
```

### Publish the configuration file

To publish the configuration file, run the following command:

```cmd
php artisan vendor:publish --provider="Condoedge\Finance\Providers\FinanceServiceProvider"
```

### Seed configuration data 

To seed the configuration data, run the following command:

```cmd
php artisan db:seed --class=\\Condoedge\\Finance\\Database\\Seeders\\SettingsSeeder
```

### Seed test data (Optional)

To seed the database with the initial data, run the following command:

```cmd
php artisan db:seed --class=\\Condoedge\\Finance\\Database\\Seeders\\BaseSeeder
```

## Extendables

### Customers

We have a table class called `customers` that is used to manage the customers of the application. This table shouldn't be called in the app if you're not touching any financial part. The idea is not add any fields in customers except the financial ones and use intermediares classes to manage other type of data. A customer could be a team, person, or other type of concept into the app. But it should implements *CustomableContract* to be able to create from them customers, being able to duplicate some fields like name, email, phone, and address. The idea is to have a single table for all the customers in the application and use the *CustomableContract* to abstract all the other logic outside the package.

You should also use *use CanBeFinancialCustomer;* to get some contract methods already resolved.

```php

// Not used for queries, just a service model to create customers from teams
class CustomableTeam extends Model implements CustomableContract
{
    use CanBeFinancialCustomer;
}
```

### Currencies

This package provides a set of methods and components for managing currencies.

#### Methods

- `finance_currency()`: Parse an amount to a string currency format using global configurations to ensure the same style across the application.

#### Components

- `_FinanceCurrency()`: Just a wrapper of finance_currency adapted to kompo to provide the html content of an amount

#### Configuration

`finance_currency` uses `get_currency_config` to get the configuration of the currency. The default configuration is set in the config file, but it's also merging that config with the global app setting: `config-currency` *app('config-currency')* so you have a dynamic way to override it using concepts like user preferences or local preferences or just change the configs files.

We're already resolving config-currency in the package
using locales and kompo-finance.currency_preformats config key, and you also can change those keys to keep the locale functionality but using different formats.

### Payment gateways

We use *PaymentGateway* facade to manage the payments methods. From there we could get the accounts of the payment methods and make transactions. The idea is to have a single point of entry for all the payment methods and be able to extend it easily. We'll have some default payment gateways like `Stripe`, `PayPal`, `Cash`, and `Bank Transfer`. We use the *PaymentGatewayResolver* giving to it the invoice context to get the right payment gateway.
The resolver will get the payment type from the invoice and create an instance of the linked payment gateway. You can configure it in kompo-finance.payment_gateways config.

```php
    // First we set the context
    PaymentGatewayResolver::setContext($invoice);

    // After that we could use the resolved payment gateway
    $account = PaymentGateway::getCashAccount()->id;
```

## Integrity ensurance explanation

The more important thing in a finantial software is ensure the integrity of the data.
I tried to avoid getters methods inside of models to ensure we could manage all the data also from the database. We have some fillable methods that will be filled using database functions so each time you want that info you'll just get it from a regular column, doing easier the order by, searchs, sums, average, and other numbers. And allowing chaning logic from the database without changing the code.
When do we calculculate those columns. There are two ways:

- We use a cron job to ensure integrity of all models and data. This is the best way to ensure that all the data is correct and up to date. The cron job will run every day and check all the models and data to ensure that everything is correct.

```cmd
php artisan condoedge:finance:integrity-checker
```

- We listen (from the application) to the events of the models and just update the data related to children and parents of those records.

So we keep this dependency to the app to run the cron job and ensure that all the data is correct. But we have a single point of truth for all the data (the database). And if you don't have the app running you can use functions instead of precalculated columns. We could change that approach just using more triggers to recalculate and fill the columns, that could be a future change

For now as i said we run a command to ensure data.
The service responsible for this is called: `Condoedge\Finance\Services\IntegrityChecker` and is using `checkChildrenThenModel()`,
`checkModelThenParents()` and `checkFullIntegrity()` methods to check the integrity of the data. And to update data in realtime (after saving the model), we use `HasIntegrityCheck` trait that is used in the models that will call a static method of the class (`checkIntegrity()`) giving the ids of the models that will be checked. This method will check the integrity of the model and update the data in realtime.

An example:

```php
    // InvoiceDetail.php

    /* INTEGRITY */
    public static function checkIntegrity($ids = null): void
    {
        DB::table('fin_invoice_details')
            ->when($ids, function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            })->update([
                'unit_price' => DB::raw('get_detail_unit_price_with_sign(fin_invoice_details.id)'),
                'tax_amount' => DB::raw('get_detail_tax_amount(fin_invoice_details.id)'),
            ]);
    }
```

The models that are using this trait are: `Condoedge\Finance\Models\Invoice`, `Condoedge\Finance\Models\Payment`, `Condoedge\Finance\Models\InvoiceDetail`,
`Condoedge\Finance\Models\InvoiceDetailTax`

You can search *@CALCULATED* into the app to find these columns. And you can also search for the functions that are used to calculate them.

We also use some triggers to ensure that the data is correct, but it's more related to creating snapshots in time (historical customers, addresses, copying taxes, invoice number) and some preventing of deleting/modifying some historical data.

You can search *@TRIGGERED* into the app to find these processes related to triggers.

## Processes explanation

TODO