# Payment Gateway Stateless Migration Guide

## 🎯 Problem Solved

The original PaymentGatewayResolver used static state which caused:
- **Concurrency issues** in high-load scenarios
- **Testing difficulties** due to shared state
- **Race conditions** in async operations
- **State pollution** between requests

## ✅ Solution: Stateless Payment Gateway System

### New Architecture:
```
PaymentGatewayService (stateless) 
    ↓
PaymentGatewayResolver (enhanced with stateless methods)
    ↓  
PaymentGatewayInterface (updated with context support)
    ↓
TempPaymentGateway (context-aware implementation)
```

## 🚀 Usage Examples

### ❌ Old Stateful Approach (Still Works):
```php
// Legacy - uses static state
PaymentGatewayResolver::setContext($invoice);
$account = PaymentGateway::getCashAccount();

// Problem: Static state can cause concurrency issues
```

### ✅ New Stateless Approach (Recommended):

#### Direct Service Usage:
```php
use Condoedge\Finance\Services\PaymentGatewayService;

$paymentService = app(PaymentGatewayService::class);

// Get cash account for specific invoice
$account = $paymentService->getCashAccountForInvoice($invoice);

// Get gateway for specific payment type
$gateway = $paymentService->getGatewayForPaymentType(PaymentTypeEnum::CASH);

// Get gateway with custom context
$gateway = $paymentService->getGatewayWithContext(PaymentTypeEnum::CASH, [
    'customer_id' => 123,
    'special_handling' => true
]);
```

#### Facade Usage (Cleaner):
```php
use Condoedge\Finance\Facades\PaymentGateway;

// Stateless facade methods
$account = PaymentGateway::getCashAccountForInvoice($invoice);
$gateway = PaymentGateway::getGatewayForInvoice($invoice);
$account = PaymentGateway::getCashAccountForPaymentType(PaymentTypeEnum::CASH);

// Get all available gateways
$gateways = PaymentGateway::getAvailableGateways();

// Validate payment type
$isValid = PaymentGateway::validatePaymentType(PaymentTypeEnum::CASH);
```

#### Controller Usage:
```php
class InvoiceController extends Controller
{
    public function store(CreateInvoiceRequest $request)
    {
        $invoice = InvoiceService::createInvoice($request->toDto());
        
        // No need for setContext() - the service handles it internally
        // The InvoiceService now uses stateless approach automatically
        
        return response()->json($invoice);
    }
    
    public function processPayment(Invoice $invoice)
    {
        // Direct stateless usage
        $gateway = PaymentGateway::getGatewayForInvoice($invoice);
        $result = $gateway->processPayment($invoice->invoice_total_amount);
        
        return response()->json($result);
    }
}
```

## 🔧 InvoiceService Integration

The InvoiceService now uses the stateless approach automatically:

```php
// Old approach in InvoiceService:
protected function setupInvoicePaymentGateway(Invoice $invoice): void
{
    PaymentGatewayResolver::setContext($invoice);  // ❌ Static state
    $invoice->account_receivable_id = PaymentGateway::getCashAccount()->id;
}

// New approach in InvoiceService:
protected function setupInvoicePaymentGateway(Invoice $invoice): void
{
    // ✅ Stateless - no shared state
    $cashAccount = $this->paymentGatewayService->getCashAccountForInvoice($invoice);
    $invoice->account_receivable_id = $cashAccount->id;
}
```

## 🧪 Testing Benefits

### Before (Problematic):
```php
class PaymentTest extends TestCase 
{
    public function test_payment_processing() 
    {
        PaymentGatewayResolver::setContext($invoice1);
        // State pollution - affects other tests
        
        PaymentGatewayResolver::setContext($invoice2);
        // Previous context lost - race condition
    }
}
```

### After (Clean):
```php
class PaymentTest extends TestCase 
{
    public function test_payment_processing() 
    {
        // No shared state - tests are isolated
        $account1 = PaymentGateway::getCashAccountForInvoice($invoice1);
        $account2 = PaymentGateway::getCashAccountForInvoice($invoice2);
        
        // Both work independently - no race conditions
    }
    
    public function test_concurrent_processing()
    {
        // Simulate concurrent requests
        $results = [];
        
        for ($i = 0; $i < 10; $i++) {
            $invoice = Invoice::factory()->create();
            $results[] = PaymentGateway::getCashAccountForInvoice($invoice);
        }
        
        // All results are valid - no static state issues
        $this->assertCount(10, $results);
    }
}
```

## 📊 Performance & Concurrency

### Concurrency Test:
```php
// This is now safe for concurrent execution
public function handleConcurrentInvoices(array $invoices): array
{
    $results = [];
    
    foreach ($invoices as $invoice) {
        // Each call is independent - no shared state
        $account = PaymentGateway::getCashAccountForInvoice($invoice);
        $results[$invoice->id] = $account;
    }
    
    return $results;
}
```

### Async Processing:
```php
use Illuminate\Support\Facades\Queue;

Queue::push(function() use ($invoice) {
    // Safe for async processing - no static state dependencies
    $gateway = PaymentGateway::getGatewayForInvoice($invoice);
    $gateway->processAsyncPayment($invoice);
});
```

## 🔄 Migration Strategy

### Phase 1: Both Approaches Available (Current)
- New stateless methods available
- Legacy methods still work
- No breaking changes

### Phase 2: Gradual Migration (Recommended)
```php
// Replace this:
PaymentGatewayResolver::setContext($invoice);
$account = PaymentGateway::getCashAccount();

// With this:
$account = PaymentGateway::getCashAccountForInvoice($invoice);
```

### Phase 3: Full Migration (Future)
- Deprecate legacy methods
- Remove static state entirely
- Clean up codebase

## 🛠️ Advanced Features

### Custom Gateway Context:
```php
$gateway = PaymentGateway::getGatewayWithContext(PaymentTypeEnum::STRIPE, [
    'webhook_secret' => config('stripe.webhook_secret'),
    'customer_id' => $invoice->customer_id,
    'metadata' => ['invoice_id' => $invoice->id]
]);
```

### Gateway Validation:
```php
$availableGateways = PaymentGateway::getAvailableGateways();

foreach ($availableGateways as $gatewayInfo) {
    $isWorking = PaymentGateway::validatePaymentType($gatewayInfo['payment_type']);
    
    if (!$isWorking) {
        Log::warning("Gateway not working: " . $gatewayInfo['label']);
    }
}
```

### Bulk Operations:
```php
$paymentService = app(PaymentGatewayService::class);

// Process multiple refunds safely
foreach ($invoices as $invoice) {
    $paymentService->processRefund($invoice);
    // Each refund is independent - no state conflicts
}
```

## ✅ Benefits Summary

1. **🔒 Thread Safety**: No shared state between requests
2. **🧪 Better Testing**: Tests are isolated and predictable  
3. **⚡ Performance**: No static state overhead
4. **🔧 Maintainability**: Cleaner, more predictable code
5. **🎯 Dependency Injection**: Proper DI instead of static coupling
6. **🚀 Scalability**: Safe for high-concurrency scenarios

## 🔗 Backward Compatibility

All existing code continues to work. Legacy methods are maintained but marked as deprecated, allowing for gradual migration without breaking changes.

```php
// ✅ This still works (legacy)
PaymentGatewayResolver::setContext($invoice);
$account = PaymentGateway::getCashAccount();

// ✅ This is the new recommended way
$account = PaymentGateway::getCashAccountForInvoice($invoice);
```

The facade pattern allows you to change implementations without breaking existing code, exactly as you pointed out! 🎯
