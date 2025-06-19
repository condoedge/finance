# GL TRANSACTIONS AND ACCOUNT SEGMENTS - COMPLETE IMPLEMENTATION

## Overview

This document details the complete implementation of the General Ledger (GL) Transactions system with the new segment-based Chart of Accounts architecture for the Condoedge Finance Package.

## Architecture Overview

### Key Components

1. **Account Segment System**
   - Flexible segment-based account structure
   - Reusable segment values across teams
   - Dynamic account creation from segment combinations

2. **GL Transaction System**
   - Header-detail structure for journal entries
   - Automatic fiscal period validation
   - Sequential transaction numbering without gaps
   - Balance validation via database triggers

3. **Service Layer Architecture**
   - Interface-based services for extensibility
   - Facades for clean API access
   - Complete backward compatibility

## Database Structure

### Account Segments Tables

```sql
-- Segment structure definitions
fin_account_segments
├── id
├── segment_description (e.g., 'Parent Team', 'Team', 'Natural Account')
├── segment_position (1, 2, 3...)
└── segment_length (number of characters)

-- Reusable segment values
fin_segment_values
├── id
├── segment_definition_id (FK to fin_account_segments)
├── segment_value (e.g., '10', '03', '4000')
├── segment_description (e.g., 'Parent Team 10', 'Cash Account')
└── is_active

-- Account creation via segment combinations
fin_account_segment_assignments
├── id
├── account_id (FK to fin_gl_accounts)
└── segment_value_id (FK to fin_segment_values)

-- Accounts table
fin_gl_accounts
├── id
├── account_id (computed: '10-03-4000')
├── account_description
├── account_type (asset/liability/equity/revenue/expense)
├── is_active
├── allow_manual_entry
└── team_id
```

### GL Transactions Tables

```sql
-- Transaction headers
fin_gl_transaction_headers
├── gl_transaction_id (e.g., '2025-01-000001')
├── gl_transaction_number (sequential)
├── fiscal_date
├── fiscal_year
├── fiscal_period
├── gl_transaction_type (1=Manual, 2=Bank, 3=Receivable, 4=Payable)
├── transaction_description
├── is_balanced (calculated by triggers)
├── is_posted
└── team_id

-- Transaction lines
fin_gl_transaction_lines
├── id
├── gl_transaction_id (FK)
├── account_id
├── line_description
├── debit_amount
└── credit_amount
```

## Component Usage

### Chart of Accounts V2

```php
// Route
Route::kompo('finance.chart-of-accounts-v2', 'ChartOfAccountsV2', 'Condoedge\Finance\Kompo\ChartOfAccounts');

// Features:
- Grouped display by natural account segment
- Advanced filtering by segments
- Account type tabs (Assets, Liabilities, etc.)
- Active/Inactive toggle
- Search functionality
- Real-time account statistics
```

### Segment Management

```php
// Route
Route::kompo('finance.segment-manager', 'SegmentManager', 'Condoedge\Finance\Kompo\SegmentManagement');

// Features:
- Segment structure definition
- Segment value management
- Bulk import capabilities
- Usage tracking
- Validation tools
```

### GL Transactions

```php
// Routes
Route::kompo('finance.gl-transactions', 'GlTransactionsTable');
Route::kompo('finance.gl-transaction-form/{id?}', 'GlTransactionForm');

// Features:
- Manual journal entry creation
- Automatic fiscal period determination
- Real-time balance validation
- Transaction posting (finalization)
- Multi-line entry support
- Account validation (active, manual entry allowed)
```

## API Endpoints

### Account Segments API

```bash
# Segment Structure
GET    /api/segments/structure
POST   /api/segments/structure
PUT    /api/segments/structure/{id}
DELETE /api/segments/structure/{id}

# Segment Values
GET    /api/segments/values/{position}
POST   /api/segments/values
PUT    /api/segments/values/{id}
DELETE /api/segments/values/{id}
POST   /api/segments/values/bulk-import

# Validation
GET    /api/segments/validate
```

### Accounts API

```bash
GET    /api/accounts                    # List with filters
GET    /api/accounts/{account_id}       # Get account details
POST   /api/accounts                    # Create from segments
PUT    /api/accounts/{id}              # Update account
GET    /api/accounts/{id}/balance      # Get balance
GET    /api/accounts/reports/trial-balance
POST   /api/accounts/bulk-create
POST   /api/accounts/search-pattern
```

### GL Transactions API

```bash
GET    /api/gl-transactions             # List with filters
GET    /api/gl-transactions/{id}        # Get transaction
POST   /api/gl-transactions             # Create transaction
PUT    /api/gl-transactions/{id}        # Update transaction
POST   /api/gl-transactions/{id}/post   # Post transaction
GET    /api/gl-transactions/account/{account_id}
GET    /api/gl-transactions/reports/unbalanced
POST   /api/gl-transactions/validate
```

### Company Default Accounts API

```bash
GET    /api/default-accounts
GET    /api/default-accounts/{setting_name}
POST   /api/default-accounts
PUT    /api/default-accounts/{setting_name}
DELETE /api/default-accounts/{setting_name}
POST   /api/default-accounts/bulk-set
```

## Service Layer Usage

### Account Creation

```php
use Condoedge\Finance\Facades\AccountSegmentService;

// Create account from segments
$account = AccountSegmentService::createAccount(
    [1 => '10', 2 => '03', 3 => '4000'],  // Segment codes by position
    [
        'account_description' => 'Main Cash Account',
        'account_type' => 'asset',
        'is_active' => true,
        'allow_manual_entry' => true,
        'team_id' => currentTeamId(),
    ]
);
// Result: Account with ID '10-03-4000'
```

### GL Transaction Creation

```php
use Condoedge\Finance\Services\GlTransactionService;
use Condoedge\Finance\Models\Dto\CreateGlTransactionDto;

$service = app(GlTransactionService::class);

$dto = new CreateGlTransactionDto([
    'fiscal_date' => '2025-06-15',
    'transaction_description' => 'Owner capital contribution',
    'lines' => [
        [
            'account_id' => '10-03-4000',
            'line_description' => 'Cash deposit',
            'debit_amount' => 10000.00,
            'credit_amount' => 0,
        ],
        [
            'account_id' => '10-03-3000',
            'line_description' => 'Owner equity',
            'debit_amount' => 0,
            'credit_amount' => 10000.00,
        ],
    ],
]);

$transaction = $service->createManualGlTransaction($dto);

// Post transaction to make it final
$service->postTransaction($transaction);
```

## Key Features

### 1. Segment-Based Accounts
- **Flexible Structure**: Define any number of segments with custom lengths
- **Reusable Values**: Segment values can be shared across accounts
- **Hierarchical Organization**: Natural grouping by segment patterns
- **Dynamic Creation**: Accounts created by combining segment values

### 2. Transaction Integrity
- **Database-Level Validation**: Triggers ensure debits = credits
- **Sequential Numbering**: No gaps in transaction numbers
- **Fiscal Period Control**: Transactions blocked for closed periods
- **Post Mechanism**: Finalized transactions cannot be modified

### 3. Multi-Module Support
- **Transaction Types**: GL, Bank, Receivable, Payable
- **Module-Specific Closing**: Close periods independently by module
- **Source Tracking**: Link GL entries to originating transactions

### 4. Advanced Features
- **Pattern-Based Search**: Find accounts by segment patterns
- **Bulk Operations**: Import segments and create accounts in bulk
- **Usage Tracking**: Monitor which segment values are in use
- **Trial Balance**: Real-time financial position reporting

## Migration from Old System

### Old System (Deprecated)
```php
// Old transaction/entry system
Transaction::create([...]);
Entry::create([...]);

// Old chart of accounts
GlAccount::createInitialAccountsIfNone($team);
```

### New System
```php
// New GL transaction system
GlTransactionService::createManualGlTransaction($dto);

// New segment-based accounts
AccountSegmentService::createAccount($segments, $attributes);
```

## Testing

Run the comprehensive test suite:

```bash
# Test account segment system
php artisan test tests/Unit/AccountSegmentSystemTest.php

# Test GL transaction system  
php artisan test tests/Unit/GlTransactionSystemTest.php

# Run all finance tests
php artisan test --filter=Finance
```

## Best Practices

1. **Always Use Services**: Don't create models directly
2. **Validate Segments**: Ensure segment combinations are valid before creating accounts
3. **Check Fiscal Periods**: Verify period is open before creating transactions
4. **Use DTOs**: Pass data via DTOs for type safety and validation
5. **Handle Exceptions**: Services throw exceptions for invalid operations

## Future Enhancements

1. **Reporting Module**: Advanced financial reports
2. **Audit Trail**: Complete transaction history
3. **Multi-Currency**: Support for foreign currency transactions
4. **Consolidation**: Multi-company consolidation features
5. **Budgeting**: Budget vs actual comparisons

## Troubleshooting

### Common Issues

1. **"Fiscal period closed"**: Check period status for the module
2. **"Account does not allow manual entry"**: Use system-generated transactions
3. **"Transaction does not balance"**: Verify debit/credit totals
4. **"Invalid segment combination"**: Check segment values exist

### Debug Commands

```bash
# Validate segment structure
php artisan finance:segment-validate

# Check fiscal period status
php artisan finance:period-status 1 --current

# Verify account structure
php artisan finance:account-check
```

---

This implementation provides a robust, scalable foundation for financial transaction management with complete data integrity and flexibility for future enhancements.
