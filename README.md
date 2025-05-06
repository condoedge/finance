# condoedge/finance

## Package Explanation

This package provides a comprehensive solution for managing financial transactions and products. Its main goal is to ensure data integrity and offer components for handling payment gateways, accounts, payment methods (extendable), invoices, accounting, taxes, and more.

---

## Installation

### 1. Install the package using Composer

```cmd
composer require condoedge/finance
```

### 2. Run migrations

Create the necessary tables in your database:

```cmd
php artisan migrate
```

### 3. Publish the configuration file

```cmd
php artisan vendor:publish --provider="Condoedge\Finance\Providers\FinanceServiceProvider"
```

### 4. Seed configuration data

```cmd
php artisan db:seed --class=\\Condoedge\\Finance\\Database\\Seeders\\SettingsSeeder
```

### 5. Seed test data (Optional)

```cmd
php artisan db:seed --class=\\Condoedge\\Finance\\Database\\Seeders\\BaseSeeder
```

---

## Extendables

### Customers

The `customers` table manages all financial customers in the application. This table should only contain financial fields. Other data should be managed through intermediary classes. A customer can be a team, person, or any other concept, but must implement the `CustomableContract` to be recognized as a customer. This allows duplicating fields like name, email, phone, and address for historical accuracy.

Use the `CanBeFinancialCustomer` trait to get contract methods already resolved.

```php
class CustomableTeam extends Model implements CustomableContract
{
    use CanBeFinancialCustomer;
}
```

### Currencies

- `finance_currency()`: Formats an amount as a currency string using global configuration.
- `_FinanceCurrency()`: Kompo component wrapper for displaying formatted amounts.

Currency configuration is dynamic and can be overridden using user or locale preferences.

### Payment Gateways

The `PaymentGateway` facade manages payment methods. All payment methods are accessed through a single entry point, making it easy to extend. Default gateways include Stripe, PayPal, Cash, and Bank Transfer. The `PaymentGatewayResolver` uses the invoice context to select the correct gateway.

```php
PaymentGatewayResolver::setContext($invoice);
$account = PaymentGateway::getCashAccount()->id;
```

---

## Ensuring Data Integrity

Data integrity is the most important aspect of financial software. This package uses several strategies to guarantee consistency:

### 1. Calculated Columns

- Instead of using model getters, important values are stored in database columns, calculated using database functions.
- This allows for efficient queries, ordering, searching, and reporting, and lets you change logic at the database level without touching the code.

### 2. Integrity Checking

There are two main ways to ensure data integrity:

- **Scheduled Cron Job:**  
  Run a command regularly (e.g., daily) to check and update all models and data:

  ```cmd
  php artisan condoedge:finance:integrity-checker
  ```
  
- **Real-time Updates:**  
  The application listens to model events and updates related data for parent/child records as needed.

The service responsible for this is `Condoedge\Finance\Services\IntegrityChecker`, which uses methods like `checkChildrenThenModel()`, `checkModelThenParents()`, and `checkFullIntegrity()`.

For real-time updates after saving a model, the `HasIntegrityCheck` trait is used. It calls a static `checkIntegrity()` method on the model, updating the relevant data based on an editable method `columnsIntegrityCalculations()` that must be implemented in each model associating columns with their functions to calculate them.

**Example:**

```php
  // AbstractMainFinanceModel.php
  public final static function checkIntegrity($ids = null): void
  {
      DB::table((new static)->getTable())
          ->when($ids, function ($query) use ($ids) {
              return $query->whereIn('id', $ids);
          })
          ->update(static::columnsIntegrityCalculations());
  }

  // InvoiceDetail.php
  public static function columnsIntegrityCalculations()
  {
      return [
            'unit_price' => DB::raw('get_detail_unit_price_with_sign(fin_invoice_details.id)'),
            'tax_amount' => DB::raw('get_detail_tax_amount(fin_invoice_details.id)'),
      ];
  }
```

Models using this trait include:  

- `Condoedge\Finance\Models\Invoice`
- `Condoedge\Finance\Models\Payment`
- `Condoedge\Finance\Models\InvoiceDetail`
- `Condoedge\Finance\Models\InvoiceDetailTax`

Search for `@CALCULATED` in the codebase to find these columns and their calculation logic.

### 3. Database Triggers

Triggers are used to:

- Create historical snapshots (e.g., customers, addresses, taxes, invoice numbers).

- Prevent deletion or modification of historical data.

Search for `@TRIGGERED` in the codebase to find these processes.

---

### 4. Component-Agnostic Logic through DTOs and Model Services

One of Kompo's strengths is its ability to automatically manage model saving within forms, which simplifies development. However, this approach has limitations:

1. **Logic Reuse Challenge**: When business logic is defined in components, it becomes difficult to reuse across different contexts
2. **Validation Isolation**: Validation rules defined at the component level cannot be easily abstracted or standardized
3. **Consistency Concerns**: Ensuring the same validation and business rules apply in all contexts (UI forms, API, etc.) becomes challenging

Our solution uses a two-part architecture:

#### Static Model Methods as Services

Instead of keeping saving logic in components, we implement static methods on model classes (or their facades) that handle all business logic:

```php
// Example: Creating an invoice from both UI and API uses the same method
public static function createInvoiceFromDto(CreateInvoiceDto $dto): self
{
    $invoice = new self();
    // Set properties from DTO
    // Apply business rules
    // Save invoice and related records
    return $invoice;
}
```

These methods act as services that encapsulate all business logic, data validation, and relationship management in one place, regardless of whether the data comes from a UI form or an API request.

#### Data Transfer Objects (DTOs)

We use the `wendelladriel/laravel-validated-dto` package to create strongly typed data containers that:

  1. Define the exact shape of data needed for each operation
  2. Implement validation rules in a single place
  3. Handle type casting automatically
  4. Provide clear contracts between UI/API and business logic

```php
// Example: DTO for creating an invoice
class CreateInvoiceDto extends ValidatedDTO
{
    public int $customer_id;
    public int $invoice_type_id;
    public Carbon $invoice_date;
    // ...

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:fin_customers,id',
            // More validation rules...
        ];
    }
}
```

This approach allows both forms and API controllers to use the exact same validation and business logic:

```php
// In InvoiceForm (UI)
public function handle()
{
  InvoiceModel::createInvoiceFromDto(new CreateInvoiceDto(request()->all()));
}

// In InvoicesController (API)
public function createInvoice(CreateInvoiceDto $data)
{
    InvoiceModel::createInvoiceFromDto($data);
    return response()->json(['message' => 'Success']);
}
```

##### Benefits

- *Consistent Data Validation:* The same rules apply everywhere
- *Separation of Concerns:* DTOs handle validation, models handle business logic
- *Type Safety:* DTOs provide strong typing and auto-completion
- *API Documentation:* DTOs automatically generate API documentation with Scramble
- *Testability:* Business logic is isolated and easily testable

## API

For apis documentation we use `dedoc/scramble`. It automatically generates a documentation using the dtos and some minor comments.

## Process Explanations

### Creating Invoices

Invoices use a system of positive and negative values to simplify balance calculations. The process is highly automated:

- **Customer Data & Addresses:**  
  Triggers automatically create historical snapshots of customer and address data at the time of invoice creation.

- **Invoice Number:**  
  A trigger assigns the next invoice number based on the invoice type.

- **Calculated Columns:**  
  After saving, the integrity process generates:
  - `invoice_amount_before_taxes`
  - `invoice_tax_amount`
  - `invoice_total_amount`
  - `invoice_due_amount`
  - `invoice_status_id`

#### Creating Invoice Details

- The sign of `unit_price` is automatically corrected based on invoice type during the integrity process.
- Tax details are created into the invoice detail service.

**Note:**  
If you use the value before saving (before the integrity process runs), you might get an incorrect value. Always use values after saving or after running the integrity process.

---

### Applying Payments

Payments involve two steps: creating the payment and applying it.

- **Creating a Payment:**  
  Payments are created in `fin_customers_payments`. This affects the customer balance but not the invoice balance.

- **Applying a Payment:**  
  Payments are applied to invoices via `fin_invoice_applies`. This updates all related values through the integrity process.

- **Integrity Enforcement:**  
  The `trg_ensure_invoice_payment_integrity` trigger ensures that applied payments do not exceed the invoice or payment amount. The `amount_left` field in `customer_payments` uses the absolute value of the payment amount, which can be positive or negative depending on the invoice type. This ensures that payments always reduce the invoice amount correctly.

---

## Maintenance of integrity

- When you're adding new financial models you should use the `HasIntegrityCheck` trait.
- Define relationships in `model_integrity_relations` config
- Implement `columnsIntegrityCalculations()` instead of getters or other type of calculation to get the columns.

## Summary

- **Data integrity** is enforced through calculated columns, scheduled checks, real-time updates, and database triggers.
- **Payments** are flexible and secure, allowing multiple payments to be applied to multiple invoices, with all balances and signs handled automatically.
- **Automation** ensures minimal manual intervention and reduces the risk of errors.

For more details, search for `@CALCULATED` and `@TRIGGERED` in the codebase to find all automated and integrity-related processes.

---
