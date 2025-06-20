# Migration Guide: Finance Package GL System

## Overview

This guide explains how to migrate from the old Transaction/Entry system to the new GL Transaction system with segment-based accounts.

## Key Changes

### 1. Account System Migration

**Old System:**
- Accounts were simple strings or codes
- No structured segments
- Limited reporting capabilities

**New System:**
- Segment-based accounts (e.g., `10-03-4000`)
- Structured hierarchy: Parent Team - Team - Natural Account
- Enhanced reporting by any segment

### 2. Transaction System Migration

**Old System:**
- Model: `Transaction` with related `Entry` models
- Manual balance checking
- No fiscal period validation

**New System:**
- Model: `GlTransactionHeader` with `GlTransactionLine`
- Automatic balance validation via database triggers
- Fiscal period enforcement
- Sequential transaction numbering without gaps

## Migration Steps

### Step 1: Set Up Account Segments

```bash
# Set up the default segment structure
php artisan finance:setup-segments --sample

# Or create custom structure
php artisan finance:setup-segments
```

### Step 2: Migrate Existing Accounts

```php
use Condoedge\Finance\Facades\AccountSegmentService;

// Example: Convert old account codes to new segment-based accounts
$oldAccounts = DB::table('fin_gl_accounts')->get();

foreach ($oldAccounts as $oldAccount) {
    // Parse your old account code logic
    $segments = [
        1 => substr($oldAccount->code, 0, 2),  // Parent Team
        2 => substr($oldAccount->code, 2, 2),  // Team  
        3 => substr($oldAccount->code, 4, 4),  // Natural Account
    ];
    
    AccountSegmentService::createAccount($segments, [
        'account_description' => $oldAccount->description,
        'account_type' => $oldAccount->type,
        'team_id' => $oldAccount->team_id,
        'is_active' => true,
        'allow_manual_entry' => true,
    ]);
}
```

### Step 3: Set Up Fiscal Year

```bash
# Set up fiscal year starting May 1, 2024 (will be FY 2025)
php artisan finance:setup-fiscal-year 1 2024-05-01

# Generate fiscal periods
php artisan finance:generate-periods 1 2025
```

### Step 4: Update UI Components

Replace old components with new ones:

**Old:**
```php
// routes/web.php
Route::kompo('finance.chart-of-accounts', 'ChartOfAccounts');
Route::kompo('finance.transactions-table', 'TransactionsTable');
```

**New:**
```php
// routes/web.php
Route::kompo('finance.chart-of-accounts-v2', 'ChartOfAccountsV2', 'Condoedge\Finance\Kompo\ChartOfAccounts');
Route::kompo('finance.gl-transactions', 'GlTransactionsTable', 'Condoedge\Finance\Kompo\GlTransactions');
```

### Step 5: Migrate Transaction Data

```php
use Condoedge\Finance\Services\GlTransactionService;
use Condoedge\Finance\Models\Dto\CreateGlTransactionDto;

$service = app(GlTransactionService::class);

// Migrate old transactions
$oldTransactions = Transaction::with('entries')->get();

foreach ($oldTransactions as $oldTx) {
    $lines = [];
    
    foreach ($oldTx->entries as $entry) {
        $lines[] = [
            'account_id' => $this->convertOldAccountCode($entry->account_id),
            'line_description' => $entry->description,
            'debit_amount' => $entry->debit ?: 0,
            'credit_amount' => $entry->credit ?: 0,
        ];
    }
    
    $dto = new CreateGlTransactionDto([
        'fiscal_date' => $oldTx->transacted_at,
        'transaction_description' => $oldTx->description,
        'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
        'team_id' => $oldTx->team_id,
        'lines' => $lines,
    ]);
    
    $service->createTransaction($dto);
}
```

## API Endpoints

### Account Management

```bash
# List accounts
GET /api/accounts

# Create account
POST /api/accounts
{
    "segments": {"1": "10", "2": "03", "3": "4000"},
    "account_description": "Cash Account",
    "account_type": "asset"
}

# Get account balance
GET /api/accounts/{accountId}/balance?start_date=2024-01-01&end_date=2024-12-31
```

### GL Transactions

```bash
# List transactions
GET /api/gl/transactions

# Create transaction
POST /api/gl/transactions
{
    "fiscal_date": "2024-06-15",
    "transaction_description": "Payment received",
    "lines": [
        {
            "account_id": "10-03-4000",
            "debit_amount": 1000,
            "credit_amount": 0
        },
        {
            "account_id": "10-03-1200",
            "debit_amount": 0,
            "credit_amount": 1000
        }
    ]
}

# Post transaction
POST /api/gl/transactions/{id}/post
```

## New Features

### 1. Segment-Based Reporting

```php
// Find all accounts for a specific team
$accounts = AccountSegmentService::searchAccountsBySegmentPattern(['*', '03', '*'], $teamId);

// Get trial balance
$trialBalance = GlAccountService::getTrialBalance($startDate, $endDate, $teamId);
```

### 2. Fiscal Period Management

```bash
# Check period status
php artisan finance:period-status 1 --current

# Close a period for GL only
php artisan finance:close-period 1 202406 --module=GL
```

### 3. Company Default Accounts

```php
// Set up in CompanyDefaultAccount model
CompanyDefaultAccount::setDefault('revenue_account', '10-03-4100', $teamId);
CompanyDefaultAccount::setDefault('expense_account', '10-03-5000', $teamId);
```

## Validation Rules

1. **Balance Validation**: All transactions must balance (debits = credits)
2. **Period Validation**: Cannot post to closed periods
3. **Account Validation**: Manual entries cannot use system-only accounts
4. **Sequential Numbering**: Transaction numbers are sequential without gaps

## Troubleshooting

### Common Issues

1. **"Period is closed" error**
   - Check period status: `php artisan finance:period-status`
   - Open period if needed: `php artisan finance:open-period`

2. **"Account not found" error**
   - Ensure accounts are created with proper segments
   - Check account is active and allows manual entry

3. **"Transaction unbalanced" error**
   - Verify sum of debits equals sum of credits
   - Check for rounding issues (use 2 decimal places)

## Best Practices

1. **Always use database transactions** when creating GL entries
2. **Validate fiscal periods** before allowing user input
3. **Use service layer** instead of models directly
4. **Test balance calculations** thoroughly
5. **Set up proper account segments** before creating accounts

## Support

For additional help:
- Check test files in `tests/Unit/Services/`
- Review service implementations in `src/Services/`
- Examine database migrations in `database/migrations/`
