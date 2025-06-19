# Fiscal Year & Period Management System

## 🎯 Overview

The Fiscal Year & Period Management system provides complete control over fiscal year configuration, period generation, and period closing operations. It enforces that transactions can only be posted to open periods, ensuring financial data integrity.

## 📊 Database Design

### FiscalYearSetup
- **Purpose**: Stores fiscal year configuration per team
- **Key Fields**: `team_id`, `company_fiscal_start_date`, `is_active`
- **Rule**: If fiscal start is 2024-05-01, fiscal year is 2025

### FiscalPeriod  
- **Purpose**: Individual fiscal periods with open/closed status per module
- **Key Fields**: `period_id`, `fiscal_year`, `start_date`, `end_date`
- **Module Flags**: `is_open_gl`, `is_open_bnk`, `is_open_rm`, `is_open_pm`

## 🚀 Command-Based Management

### 1. Setup Fiscal Year
```bash
# Basic setup
php artisan finance:setup-fiscal-year 1 2024-05-01

# Force override existing setup
php artisan finance:setup-fiscal-year 1 2024-05-01 --force
```

**Example Output:**
```
Setting up fiscal year for team 1...
Fiscal start date: 2024-05-01
Calculated fiscal year: 2025
✓ Fiscal year setup completed successfully!
```

### 2. Generate Fiscal Periods
```bash
# Generate monthly periods for fiscal year 2025
php artisan finance:generate-periods 1 2025

# Regenerate (delete and recreate)
php artisan finance:generate-periods 1 2025 --regenerate

# Custom periods (interactive)
php artisan finance:generate-periods 1 2025 --custom
```

**Generated Periods Example:**
```
Period ID    | Period Number | Start Date | End Date   | Status
per01-2025   | 1            | 2024-05-01 | 2024-05-31 | Open (All Modules)
per02-2025   | 2            | 2024-06-01 | 2024-06-30 | Open (All Modules)
per03-2025   | 3            | 2024-07-01 | 2024-07-31 | Open (All Modules)
...
```

### 3. Close Fiscal Periods
```bash
# Close specific modules
php artisan finance:close-period per01-2025 --modules=GL,BNK

# Close all modules
php artisan finance:close-period per01-2025 --all

# Interactive closing
php artisan finance:close-period per01-2025

# Skip confirmation
php artisan finance:close-period per01-2025 --all --force
```

### 4. Open Fiscal Periods  
```bash
# Open specific modules
php artisan finance:open-period per01-2025 --modules=GL,BNK

# Open all modules
php artisan finance:open-period per01-2025 --all

# Interactive opening
php artisan finance:open-period per01-2025
```

### 5. View Period Status

#### View Current Fiscal Year
```bash
php artisan finance:period-status 1 --current
```

#### View Specific Fiscal Year
```bash
php artisan finance:period-status 1 --fiscal_year=2025
```

#### View Specific Period
```bash
php artisan finance:period-status 1 --period=per01-2025
```

**Status Output (Your Requested Format):**
```
=== Fiscal Year 2025 Status ===
PERIOD                           IS OPEN (1) OR IS CLOSED (0)
                                 GL     BNK     RM     PM
----------------------------------------------------------------------
per01 from 2024-05-01 to 2024-05-31    1      1       1      1
per02 from 2024-06-01 to 2024-06-30    0      0       0      0
per03 from 2024-07-01 to 2024-07-31    1      0       1      1
```

## 💻 Programmatic Usage

### Using the Service Directly
```php
use Condoedge\Finance\Services\FiscalYearService;

$fiscalService = app(FiscalYearService::class);

// Setup fiscal year
$setup = $fiscalService->setupFiscalYear(1, Carbon::parse('2024-05-01'));

// Generate periods
$periods = $fiscalService->generateFiscalPeriods(1, 2025);

// Close period
$closedPeriod = $fiscalService->closePeriod('per01-2025', ['GL', 'BNK']);

// Validate transaction date
$fiscalService->validateTransactionDate(Carbon::now(), 'GL', 1);
```

### Using the Facade
```php
use Condoedge\Finance\Facades\FiscalYearService;

// Setup fiscal year
$setup = FiscalYearService::setupFiscalYear(1, Carbon::parse('2024-05-01'));

// Get current period
$currentPeriod = FiscalYearService::getCurrentPeriod(1);

// Get fiscal year summary
$summary = FiscalYearService::getFiscalYearSummary(1, 2025);
```

## 🔒 Transaction Validation

### Automatic Validation with Trait
```php
use Condoedge\Finance\Models\Traits\ValidatesFiscalPeriod;

class GlTransactionHeader extends Model
{
    use ValidatesFiscalPeriod;
    
    // The trait automatically validates fiscal periods on create/update
    
    protected $fiscalModule = 'GL'; // Override default module
    
    protected function getFiscalDateForValidation(): ?Carbon
    {
        return $this->fiscal_date; // Override date field
    }
}
```

### Manual Validation
```php
use Condoedge\Finance\Facades\FiscalYearService;

// Validate before creating transaction
try {
    FiscalYearService::validateTransactionDate(
        Carbon::parse('2024-05-15'), 
        'GL', 
        1
    );
    
    // Safe to create transaction
    $transaction = new GlTransactionHeader([...]);
    $transaction->save();
    
} catch (ValidationException $e) {
    // Period is closed - handle error
    throw new \Exception("Cannot post transaction: {$e->getMessage()}");
}
```

### Bypass Validation (System Transactions)
```php
$transaction = new GlTransactionHeader([...]);

// Temporarily disable validation for system-generated transactions
$transaction->withoutFiscalPeriodValidation(function() use ($transaction) {
    $transaction->save();
});
```

## 📈 Business Logic

### Fiscal Year Calculation
- **Rule**: Fiscal Year = Start Date Year + 1
- **Example**: Start date 2024-05-01 → Fiscal Year 2025
- **Period Range**: 2024-05-01 to 2025-04-30

### Period Generation
- **Monthly Periods**: 12 periods (per01 through per12)
- **Custom Periods**: Support for non-monthly periods
- **Validation**: No gaps or overlaps allowed

### Module-Based Closing
- **GL**: General Ledger transactions
- **BNK**: Bank transactions  
- **RM**: Receivables Management
- **PM**: Payables Management

### Transaction Posting Rules
- ✅ **Open Period**: Transactions allowed
- ❌ **Closed Period**: Transactions blocked with validation error
- 🔧 **System Override**: Bypass validation for system transactions

## ⚙️ Configuration & Setup

### Initial Setup Workflow
```bash
# 1. Setup fiscal year
php artisan finance:setup-fiscal-year 1 2024-05-01

# 2. Generate periods
php artisan finance:generate-periods 1 2025

# 3. View status
php artisan finance:period-status 1 --current

# 4. Close completed periods
php artisan finance:close-period per01-2025 --all
```

### Integration with GL Transactions
```php
// In GlTransactionHeader model
use Condoedge\Finance\Models\Traits\ValidatesFiscalPeriod;

class GlTransactionHeader extends Model
{
    use ValidatesFiscalPeriod;
    
    // Automatically validates on save
}
```

## 🛡️ Data Integrity Features

### Validation Rules
- Fiscal periods cannot overlap
- Closed periods prevent transaction posting
- System transactions can bypass validation
- Period regeneration blocked if periods are closed

### Audit Trail
- Period closing/opening actions are logged
- User and timestamp tracked for changes
- Exception handling for invalid operations

### Business Rules
- Cannot delete periods with transactions
- Cannot regenerate closed periods
- Future-dated transactions validated against period status

## 📊 Reporting & Monitoring

### Period Status Monitoring
```php
// Get fiscal year summary
$summary = FiscalYearService::getFiscalYearSummary(1, 2025);

echo "Total Periods: {$summary['total_periods']}";
echo "GL Fully Closed: " . ($summary['closure_status']['GL'] ? 'Yes' : 'No');

// Individual period status
foreach ($summary['periods'] as $periodData) {
    $period = $periodData['period'];
    echo "{$period->period_id}: GL=" . ($period->is_open_gl ? 'Open' : 'Closed');
}
```

### Current Period Detection
```php
// Get current period for today
$currentPeriod = FiscalYearService::getCurrentPeriod(1);

if ($currentPeriod) {
    echo "Current period: {$currentPeriod->period_id}";
    echo "GL Open: " . ($currentPeriod->is_open_gl ? 'Yes' : 'No');
}
```

This fiscal year management system provides the **exact functionality** you requested with command-based management, period-level module closing, and robust transaction validation that prevents posting to closed periods.
