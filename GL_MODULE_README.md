# General Ledger (GL) Module

## Overview

The General Ledger module is a comprehensive accounting system that provides foundational functionality for financial accounting and reporting. It implements all the requirements specified in the Finance Module: General Ledger Transactions Requirements document.

## Key Features

### 1. Fiscal Configuration & Period Management
- **Fiscal Year Setup**: Configure company fiscal start date
- **Fiscal Period Management**: Create and manage fiscal periods with start/end dates
- **Period Closing**: Close/open periods by module (GL, BNK, RM, PM)
- **Period Validation**: Prevent transactions in closed periods

### 2. Chart of Accounts & Account Management
- **Flexible Account Structure**: Define segments with configurable lengths
- **Segment Management**: Create and manage segment values with descriptions
- **Account Creation**: Build accounts from segment combinations
- **Account Control**: Enable/disable accounts and manual entry permissions
- **Account Types**: Support for Asset, Liability, Equity, Revenue, and Expense accounts

### 3. GL Transaction Processing
- **Manual Journal Entries**: Create manual GL transactions with multiple entries
- **System Transactions**: Support for transactions from other modules (Bank, Receivable, Payable)
- **Transaction Validation**: Enforce debit/credit balance and period open validation
- **Sequential Numbering**: Automatic, unbroken GL transaction numbering
- **Audit Trail**: Complete audit tracking with created/modified by and timestamps

### 4. Company Default Accounts
- **Centralized Defaults**: Configure default accounts for various transaction types
- **Fallback System**: Automatic account selection when specific accounts aren't defined
- **Configurable Types**: Revenue, Expense, Bank, AP, AR, COGS, and more

## Database Structure

### Core Tables

#### Fiscal Management
- `fin_fiscal_year_setup`: Company fiscal year configuration
- `fin_fiscal_periods`: Individual fiscal periods with module-specific open/closed status

#### Account Structure
- `fin_account_segment_definitions`: Define account segment structure (position, length, name)
- `fin_gl_segment_values`: Store segment structure definitions and individual segment values
- `fin_accounts`: Enhanced GL accounts with segment values and control flags
- `fin_company_default_accounts`: Company-level default account settings

#### Transactions
- `fin_transactions`: Enhanced with GL-specific fields (fiscal date, year, period, type)
- `fin_entries`: GL transaction lines with account references and amounts
- `fin_vendors`: Vendor master for payable transactions

## Installation & Setup

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Seed Sample Data
```bash
php artisan db:seed --class="Condoedge\Finance\Database\Seeders\GL\GlSetupSeeder"
php artisan db:seed --class="Condoedge\Finance\Database\Seeders\GL\ChartOfAccountsSeeder"
```

### 3. Configure Account Structure
The seeder creates a sample structure: XX-XXX-XXXX
- Segment 1: Project (2 characters)
- Segment 2: Activity (3 characters)  
- Segment 3: Natural Account (4 characters)

## Usage Examples

### Creating a Manual GL Transaction

```php
use Condoedge\Finance\Services\GL\GlTransactionService;
use Carbon\Carbon;

$glService = app(GlTransactionService::class);

$entries = [
    [
        'account_id' => '10-705-1105',
        'line_description' => 'Cash received from customer',
        'debit_amount' => 1000.00,
        'credit_amount' => 0
    ],
    [
        'account_id' => '10-705-1200',
        'line_description' => 'Reduce accounts receivable',
        'debit_amount' => 0,
        'credit_amount' => 1000.00
    ]
];

$transaction = $glService->createManualGlTransaction(
    'Customer payment received',
    Carbon::now(),
    $entries
);
```

### Setting Up Account Structure

```php
use Condoedge\Finance\Services\GL\ChartOfAccountsService;

$chartService = app(ChartOfAccountsService::class);

// Define account structure
$segments = [
    ['name' => 'Division', 'length' => 2, 'description' => 'Business division'],
    ['name' => 'Department', 'length' => 3, 'description' => 'Department code'],
    ['name' => 'Account', 'length' => 4, 'description' => 'Natural account']
];

$chartService->setupAccountStructure($segments);

// Create segment values
$chartService->createSegmentValue(1, '01', 'Corporate Division');
$chartService->createSegmentValue(2, '100', 'Administration');
$chartService->createSegmentValue(3, '1105', 'Operating Cash');

// Create GL account
$account = $chartService->createGlAccount(
    ['01', '100', '1105'],
    'Corporate Administration Cash Account',
    'Asset',
    'Current Assets'
);
```

### Managing Fiscal Periods

```php
use Condoedge\Finance\Services\GL\FiscalPeriodService;

$fiscalService = app(FiscalPeriodService::class);

// Create fiscal periods
$periods = $fiscalService->createFiscalPeriods(
    2025,
    Carbon::parse('2024-05-01'),
    12
);

// Close a period for GL transactions
$fiscalService->closePeriod('per01', ['GL']);

// Check period status
$status = $fiscalService->getPeriodStatus('per01');
```

## API Endpoints

### Fiscal Period Management
- `GET /api/gl/fiscal/setup` - Get fiscal year setup
- `POST /api/gl/fiscal/setup` - Create/update fiscal year setup
- `POST /api/gl/fiscal/periods` - Create fiscal periods
- `GET /api/gl/fiscal/periods` - Get fiscal periods
- `POST /api/gl/fiscal/periods/{periodId}/close` - Close period
- `POST /api/gl/fiscal/periods/{periodId}/open` - Open period

### Chart of Accounts
- `POST /api/gl/accounts/structure` - Setup account structure
- `GET /api/gl/accounts/structure` - Get account structure
- `POST /api/gl/accounts/segments/values` - Create segment value
- `GET /api/gl/accounts/segments/{segmentNumber}/values` - Get segment values
- `GET /api/gl/accounts/` - Get chart of accounts
- `POST /api/gl/accounts/` - Create GL account
- `PUT /api/gl/accounts/{accountId}` - Update GL account
- `GET /api/gl/accounts/trial-balance` - Get trial balance

### GL Transactions
- `GET /api/gl/transactions/` - Get GL transactions
- `GET /api/gl/transactions/{transactionId}` - Get specific transaction
- `POST /api/gl/transactions/manual` - Create manual GL transaction
- `POST /api/gl/transactions/system` - Create system GL transaction
- `PUT /api/gl/transactions/{transactionId}` - Update transaction
- `DELETE /api/gl/transactions/{transactionId}` - Delete transaction
- `POST /api/gl/transactions/{transactionId}/reverse` - Reverse transaction

## Web Interface Routes

### Setup Pages
- `/gl/fiscal-setup` - Fiscal year and period setup
- `/gl/account-structure` - Account structure configuration
- `/gl/chart-of-accounts` - Chart of accounts management

### Transaction Pages
- `/gl/transactions` - Transaction listing and management
- `/gl/transactions/create` - Create new manual transaction
- `/gl/transactions/{id}/edit` - Edit existing transaction

### Reports
- `/gl/reports/trial-balance` - Trial balance report
- `/gl/reports/general-ledger` - General ledger report

## Integration with Other Modules

### Bank Module Integration
```php
// Create GL transaction from bank transaction
$glService->createSystemGlTransaction(
    GlTransaction::TYPE_BANK,
    'Bank deposit',
    $bankTransaction->transaction_date,
    [
        [
            'account_id' => CompanyDefaultAccount::getDefaultBankAccount(),
            'debit_amount' => $amount,
            'credit_amount' => 0
        ],
        [
            'account_id' => $revenueAccount,
            'debit_amount' => 0,
            'credit_amount' => $amount
        ]
    ],
    ['originating_module_transaction_id' => $bankTransaction->id]
);
```

### Receivable Module Integration
```php
// Create GL transaction from invoice
$glService->createSystemGlTransaction(
    GlTransaction::TYPE_RECEIVABLE,
    "Invoice #{$invoice->invoice_number}",
    $invoice->invoice_date,
    [
        [
            'account_id' => CompanyDefaultAccount::getDefaultAccountsReceivable(),
            'debit_amount' => $invoice->total_amount,
            'credit_amount' => 0
        ],
        [
            'account_id' => CompanyDefaultAccount::getDefaultRevenueAccount(),
            'debit_amount' => 0,
            'credit_amount' => $invoice->total_amount
        ]
    ],
    [
        'originating_module_transaction_id' => $invoice->id,
        'customer_id' => $invoice->customer_id
    ]
);
```

## Validation Rules

### Transaction Validation
1. **Balance Validation**: Total debits must equal total credits
2. **Period Validation**: Fiscal period must be open for the transaction type
3. **Account Validation**: Accounts must exist and be active
4. **Manual Entry Validation**: Manual GL transactions cannot use accounts with `allow_manual_entry = false`

### Account Structure Validation
1. **Segment Length**: Segment values must match defined lengths
2. **Segment Existence**: All segment values must exist before creating accounts
3. **Unique Accounts**: Account IDs must be unique across the system

### Fiscal Period Validation
1. **Date Ranges**: Period end date must be after start date
2. **No Overlapping**: Periods cannot overlap for the same fiscal year
3. **Sequential Periods**: Periods should be sequential within a fiscal year

## Reporting Features

### Trial Balance
- Account balances as of a specific date
- Grouped by account type (Asset, Liability, Equity, Revenue, Expense)
- Segment-based filtering and grouping
- Debit/Credit balance presentation

### General Ledger
- Detailed transaction history by account
- Date range filtering
- Transaction drill-down capability
- Running balance calculations

### Financial Statements
The GL module provides the foundation for:
- Balance Sheet
- Income Statement (P&L)
- Cash Flow Statement
- Segment-based reporting

## Error Handling

### Common Exceptions
- `\Exception`: General GL operation failures
- `ValidationException`: Input validation failures
- `ModelNotFoundException`: Record not found errors

### Error Messages
- Period closed errors include specific module and period information
- Account validation errors specify which validation rule failed
- Balance validation errors show the exact imbalance amount

## Performance Considerations

### Indexing
All tables include appropriate indexes for:
- Primary and foreign keys
- Frequently queried fields (dates, account types, status flags)
- Composite indexes for common query patterns

### Caching
- Account structure definitions are cached for performance
- Default account lookups are optimized
- Segment value lookups use efficient queries

### Bulk Operations
- Support for bulk account creation via import
- Efficient trial balance calculations
- Optimized period closing operations

## Security & Permissions

### Access Control
- Role-based permissions for setup operations
- Transaction-level access control
- Period closing permissions
- Audit trail protection

### Data Integrity
- Foreign key constraints ensure referential integrity
- Check constraints prevent invalid data
- Transaction-level locks prevent concurrent modification issues

## Testing

### Unit Tests
- Model validation tests
- Service layer tests
- API endpoint tests

### Integration Tests
- End-to-end transaction flows
- Inter-module integration tests
- Period closing workflows

### Sample Test Data
The seeders provide comprehensive test data including:
- Sample fiscal periods
- Complete chart of accounts
- Various transaction types
- Default account configurations

## Configuration Options

### Decimal Precision
Configure decimal precision in `config/kompo-finance.php`:
```php
'decimal-scale' => 5, // Number of decimal places for amounts
```

### Default Fiscal Start
The default fiscal year starts May 1st, but can be configured during setup.

### Account Structure Flexibility
Support for 1-5 segments with configurable lengths (1-10 characters each).

## Troubleshooting

### Common Issues

1. **Transaction Won't Save - Not Balanced**
   - Verify total debits equal total credits
   - Check for rounding errors in decimal calculations

2. **Period Closed Error**
   - Check fiscal period status for the specific module
   - Verify transaction date falls within an open period

3. **Account Not Found**
   - Ensure account exists in chart of accounts
   - Verify account is active
   - Check segment values are properly defined

4. **Sequential Number Gaps**
   - GL transaction numbers are automatically generated
   - Gaps may indicate deleted transactions or system errors
   - Use database queries to identify and investigate gaps

## Future Enhancements

### Planned Features
- Multi-currency support
- Advanced segment reporting
- Automated journal entry templates  
- Integration with budgeting modules
- Enhanced audit trail with change tracking
- Batch transaction processing
- Advanced approval workflows

### Extensibility
The GL module is designed for extensibility:
- Pluggable validation rules
- Custom transaction types
- Additional segment definitions
- Custom report builders
- Third-party integration points

## Support

For technical support and questions:
1. Check the API documentation for endpoint details
2. Review error logs for specific error messages
3. Use the provided test cases as examples
4. Refer to the database schema for data relationships

This GL module provides a solid foundation for financial accounting while maintaining flexibility for various business requirements and growth scenarios.
