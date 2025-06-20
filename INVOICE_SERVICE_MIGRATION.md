# Invoice Service Migration Guide

## 🎯 Overview

The Invoice model business logic has been extracted into a dedicated service layer following the existing architecture patterns in the project. This improves testability, maintainability, and allows for easy customization.

## 🚨 Critical Architectural Issues Fixed

### Before (Fat Model Antipattern):
```php
// ❌ Business logic mixed in model
class Invoice extends Model {
    public static function createInvoiceFromDto($dto) {
        // Complex business logic here
        PaymentGatewayResolver::setContext($invoice); // Static coupling
        $customer->fillInvoiceForCustomer($invoice);   // Mixed responsibilities
        // More complex logic...
    }
}
```

### After (Clean Service Layer):
```php
// ✅ Clean separation of concerns
class InvoiceService implements InvoiceServiceInterface {
    public function createInvoice(CreateInvoiceDto $dto): Invoice {
        return DB::transaction(function () use ($dto) {
            $this->validateCreateInvoiceBusinessRules($dto);
            $invoice = $this->createBaseInvoice($dto);
            $this->setupInvoicePaymentGateway($invoice);
            // Clean, testable, separated logic
        });
    }
}
```

## 📁 New Files Created

```
src/Services/Invoice/
├── InvoiceServiceInterface.php    # Interface for easy override
├── InvoiceService.php            # Main implementation
└── CustomInvoiceService.php      # Example custom implementation

src/Facades/
└── InvoiceService.php            # Facade for easy access

tests/Unit/Services/Invoice/
└── InvoiceServiceTest.php        # Unit tests
```

## 🔧 How to Use

### New Recommended Way (Use the Service):
```php
use Condoedge\Finance\Facades\InvoiceService;

// Create invoice
$invoice = InvoiceService::createInvoice($createDto);

// Update invoice  
$invoice = InvoiceService::updateInvoice($updateDto);

// Approve invoice
$invoice = InvoiceService::approveInvoice($approveDto);

// Approve many
$invoices = InvoiceService::approveMany($approveManyDto);
```

### Legacy Way (Still Works):
```php
// ⚠️ Deprecated but maintained for backward compatibility
$invoice = Invoice::createInvoiceFromDto($dto);
$invoice = Invoice::updateInvoiceFromDto($dto);
```

## 🎨 Easy Customization

### 1. Override Specific Methods:
```php
class MyCustomInvoiceService extends InvoiceService
{
    protected function validateCreateInvoiceBusinessRules(CreateInvoiceDto $dto): void
    {
        parent::validateCreateInvoiceBusinessRules($dto);
        
        // Add your custom validation
        if ($dto->customer_id === 123) {
            throw new \Exception('This customer is blacklisted');
        }
    }
}
```

### 2. Complete Custom Implementation:
```php
class CompanySpecificInvoiceService implements InvoiceServiceInterface
{
    // Implement all interface methods with your business logic
}
```

### 3. Register Your Custom Service:
```php
// In AppServiceProvider
$this->app->bind(InvoiceServiceInterface::class, MyCustomInvoiceService::class);
```

## 🧪 Testing Benefits

### Before (Hard to Test):
```php
// ❌ Static methods are nightmare to mock
class SomeControllerTest extends TestCase {
    public function test_something() {
        // Can't easily mock Invoice::createInvoiceFromDto()
        // Can't inject dependencies
        // Difficult to isolate business logic
    }
}
```

### After (Easy to Test):
```php
// ✅ Easy to mock and test
class SomeControllerTest extends TestCase {
    public function test_something() {
        $mockService = $this->createMock(InvoiceServiceInterface::class);
        $mockService->expects($this->once())
                   ->method('createInvoice')
                   ->willReturn($expectedInvoice);
        
        $this->app->instance(InvoiceServiceInterface::class, $mockService);
        // Now test your controller logic in isolation
    }
}
```

## 🔍 Architecture Benefits

1. **Single Responsibility**: Each method has one clear purpose
2. **Dependency Injection**: Proper DI instead of static coupling
3. **Transaction Management**: Proper DB transaction handling
4. **Error Handling**: Consistent exception handling patterns
5. **Validation**: Business rule validation separated from model
6. **Testability**: Easy to unit test and mock
7. **Extensibility**: Interface allows complete override
8. **Consistency**: Follows same patterns as GlTransactionService

## ⚠️ Migration Strategy

### Phase 1: Install (Current)
- New service is available
- Legacy methods still work
- No breaking changes

### Phase 2: Migrate (Recommended)
- Update controllers to use service facade
- Update tests to use service
- Add custom business logic via service override

### Phase 3: Cleanup (Future)
- Remove deprecated static methods
- Clean model to focus on data/relationships only

## 🚨 Architectural Notes

### 1. PaymentGatewayResolver Static State
```php
// ⚠️ MINOR CONCERN: Static state can cause concurrency issues
PaymentGatewayResolver::setContext($invoice);

// 💡 POTENTIAL IMPROVEMENT: Stateless approach
protected function setupInvoicePaymentGateway(Invoice $invoice): void
{
    // This would eliminate static state:
    $account = PaymentGateway::getCashAccountForInvoice($invoice);
    $invoice->account_receivable_id = $account->id;
}
```
**Note**: The facade pattern itself is excellent and allows implementation changes.

### 2. Database Calculations (CORRECT APPROACH)
```php
// ✅ EXCELLENT: Database triggers ensure absolute integrity
DB::raw('calculate_invoice_due(fin_invoices.id)')

// Why this is RIGHT for financial systems:
// - Guarantees calculations even with direct DB access
// - Prevents data inconsistencies at the database level
// - Required for financial compliance and auditing
// - Performance benefits for complex aggregations
```

## 📊 Performance Considerations

The service layer adds minimal overhead while providing massive architecture benefits:
- **+** Better caching opportunities
- **+** Easier query optimization
- **+** Better transaction management
- **+** Reduced model complexity
- **=** Same database queries (for now)

## 🎯 Next Steps

1. **Immediate**: Start using `InvoiceService::` in new code
2. **Short term**: Migrate existing controllers to use service
3. **Medium term**: Add custom business logic via service override
4. **Long term**: Consider making PaymentGatewayResolver stateless (optional improvement)

## 🛡️ Backward Compatibility

All existing code continues to work. The old static methods now delegate to the service internally, ensuring no breaking changes while encouraging migration to the better architecture.
