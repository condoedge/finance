<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceableInterface;
use Condoedge\Finance\Models\InvoiceTypeEnum;
use Condoedge\Finance\Models\PaymentInstallmentPeriod;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Utils\Models\Model;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\Mocks\MockPaymentProvider;
use Tests\TestCase;

/**
 * Mock Invoiceable Model for testing
 */
class MockInvoiceableModel implements InvoiceableInterface
{
    public $completePaymentCalled = false;
    public $partialPaymentCalled = false;

    public function onCompletePayment(): void
    {
        $this->completePaymentCalled = true;
    }

    public function onPartialPayment(): void
    {
        $this->partialPaymentCalled = true;
    }
}

class AbstractPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    protected MockPaymentProvider $provider;
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $this->user = UserFactory::new()->create()->first();
        if (!$this->user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($this->user);

        $this->provider = new MockPaymentProvider();
    }

    /**
     * Test successful payment execution
     */
    public function test_it_executes_sale_successfully()
    {
        $invoice = $this->createTestInvoice(500);
        $this->provider->setShouldSucceed(true);

        $request = [
            'invoice' => $invoice,
            'amount' => 500,
            'card_information' => '4111111111111111',
            'complete_name' => 'Test User',
            'expiration_date' => '12/25',
            'card_cvc' => '123',
        ];

        $callbackExecuted = false;
        $result = $this->provider->executeSale($request, function ($response) use (&$callbackExecuted) {
            $callbackExecuted = true;
        });

        $this->assertTrue($result);
        $this->assertTrue($callbackExecuted);
        $this->assertTrue($this->provider->wasSaleCreated());
    }

    /**
     * Test failed payment handling
     */
    public function test_it_handles_failed_payment_properly()
    {
        $invoice = $this->createTestInvoice(500);
        $this->provider->setShouldSucceed(false);

        $request = [
            'invoice' => $invoice,
            'amount' => 500,
        ];

        try {
            $this->provider->executeSale($request);
            $this->fail('Expected exception was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals(__('error-payment-failed'), $e->getMessage());
        }
    }

    /**
     * Test payment record creation on success
     */
    public function test_it_creates_payment_record_on_success()
    {
        $invoice = $this->createTestInvoice(300);
        $this->provider->setShouldSucceed(true);
        $this->provider->setResponseData([
            'amount' => 300,
            'referenceUUID' => 'EXT-12345',
        ]);

        $request = ['invoice' => $invoice];

        $this->provider->executeSale($request);

        // Check payment was created
        $this->assertDatabaseHas('fin_customer_payments', [
            'amount' => db_decimal_format(300),
            'external_reference' => 'EXT-12345',
        ]);

        // Check payment was applied to invoice
        $invoice->refresh();
        $this->assertEqualsDecimals(0, $invoice->invoice_due_amount);
    }

    /**
     * Test success callback execution
     */
    public function test_it_calls_success_callback_when_provided()
    {
        $invoice = $this->createTestInvoice(200);
        $this->provider->setShouldSucceed(true);

        $callbackResponse = null;
        $request = ['invoice' => $invoice];

        $this->provider->executeSale($request, function ($response) use (&$callbackResponse) {
            $callbackResponse = $response;
        });

        $this->assertNotNull($callbackResponse);
        $this->assertArrayHasKey('status', $callbackResponse);
        $this->assertEquals('APPROVED', $callbackResponse['status']);
    }

    /**
     * Test context initialization
     */
    public function test_it_initializes_context_properly()
    {
        $invoice = $this->createTestInvoice(100);
        $installmentIds = [1, 2, 3];

        $context = [
            'invoice' => $invoice,
            'installment_ids' => $installmentIds,
        ];

        $this->provider->initializeContext($context);

        // Test that context was set by trying to execute sale
        $this->provider->setShouldSucceed(true);
        $result = $this->provider->executeSale([]);

        $this->assertTrue($result);
    }

    /**
     * Test complete payment event handling
     */
    public function test_it_handles_invoice_complete_payment_events()
    {
        $mockInvoiceable = new MockInvoiceableModel();
        $invoice = $this->createTestInvoice(100);

        // Associate the mock invoiceable
        $invoice->invoiceable_type = get_class($mockInvoiceable);
        $invoice->invoiceable_id = 1;
        $invoice->save();

        $invoice = \Mockery::mock($invoice)->makePartial();
        $invoice->shouldReceive('refresh')->once()
            ->andReturnUsing(function () use ($invoice, $mockInvoiceable) {
                // Manually refresh attributes
                $freshModel = $invoice->fresh();
                if ($freshModel) {
                    $invoice->setRawAttributes($freshModel->getAttributes(), true);
                }

                // Re-set the relation
                $invoice->setRelation('invoiceable', $mockInvoiceable);

                return $invoice;
            });

        // Mock the relationship
        $invoice->setRelation('invoiceable', $mockInvoiceable);

        $this->provider->setShouldSucceed(true);
        $this->provider->setResponseData(['amount' => $invoice->invoice_total_amount]);

        $request = ['invoice' => $invoice];
        $this->provider->executeSale($request);

        // Invoice should be fully paid, triggering complete payment
        $this->assertTrue($mockInvoiceable->completePaymentCalled);
        $this->assertFalse($mockInvoiceable->partialPaymentCalled);
    }

    /**
     * Test partial payment event handling
     */
    public function test_it_handles_invoice_partial_payment_events()
    {
        $mockInvoiceable = new MockInvoiceableModel();
        $invoice = $this->createTestInvoice(200);

        // Associate the mock invoiceable
        $invoice->invoiceable_type = get_class($mockInvoiceable);
        $invoice->invoiceable_id = 1;
        $invoice->save();

        $invoice = \Mockery::mock($invoice)->makePartial();
        $invoice->shouldReceive('refresh')->once()
            ->andReturnUsing(function () use ($invoice, $mockInvoiceable) {
                // Manually refresh attributes
                $freshModel = $invoice->fresh();
                if ($freshModel) {
                    $invoice->setRawAttributes($freshModel->getAttributes(), true);
                }

                // Re-set the relation
                $invoice->setRelation('invoiceable', $mockInvoiceable);

                return $invoice;
            });

        $invoice->setRelation('invoiceable', $mockInvoiceable);

        $this->provider->setShouldSucceed(true);
        $this->provider->setResponseData(['amount' => 50]); // Partial payment

        $request = ['invoice' => $invoice];
        $this->provider->executeSale($request);

        // Invoice should be partially paid
        $this->assertFalse($mockInvoiceable->completePaymentCalled);
        $this->assertTrue($mockInvoiceable->partialPaymentCalled);
    }

    /**
     * Test validation that invoice must be set
     */
    public function test_it_validates_invoice_is_set_before_payment()
    {
        // Don't set invoice in context
        $this->provider->setShouldSucceed(true);

        try {
            // This should trigger the ensureInvoiceIsSet check
            $this->provider->onSuccessTransaction(100, 'REF-123');
            $this->fail('Expected exception was not thrown');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals(__('error-payment-cannot-be-completed'), $e->getMessage());
        }
    }

    /**
     * Test installment payment processing
     */
    public function test_it_processes_installment_payments_correctly()
    {
        $invoice = $this->createTestInvoice(1000);

        // Create installment periods
        $installments = [];
        for ($i = 1; $i <= 3; $i++) {
            $installment = new PaymentInstallmentPeriod();
            $installment->invoice_id = $invoice->id;
            $installment->installment_number = $i;
            $installment->amount = 333.33;
            $installment->due_amount = 333.33;
            $installment->save();
            $installments[] = $installment;
        }

        $context = [
            'invoice' => $invoice,
            'installment_ids' => array_column($installments, 'id'),
        ];

        $this->provider->initializeContext($context);
        $this->provider->setShouldSucceed(true);

        // Provider should generate payable lines for installments
        $payableLines = $this->invokeMethod($this->provider, 'getPayableLines');

        $this->assertCount(3, $payableLines);
        foreach ($payableLines as $index => $line) {
            $this->assertStringContainsString(__('finance-installment-period', ['installment_number' => $index + 1]), $line->description);
            $this->assertEqualsDecimals(333.33, $line->amount);
        }
    }

    /**
     * Test payable lines generation from invoice details
     */
    public function test_it_generates_correct_payable_lines()
    {
        $invoice = $this->createTestInvoice(500);

        $context = ['invoice' => $invoice];
        $this->provider->initializeContext($context);

        $payableLines = $this->invokeMethod($this->provider, 'getPayableLines');

        $this->assertCount(1, $payableLines);
        $this->assertEquals('Test Item', $payableLines->first()->description);
        $this->assertEqualsDecimals(500, $payableLines->first()->amount);
    }

    /**
     * Test external reference handling
     */
    public function test_it_stores_external_reference_correctly()
    {
        $invoice = $this->createTestInvoice(150);
        $externalRef = 'GATEWAY-REF-' . uniqid();

        $this->provider->setShouldSucceed(true);
        $this->provider->setResponseData([
            'amount' => 150,
            'referenceUUID' => $externalRef,
        ]);

        $request = ['invoice' => $invoice];
        $this->provider->executeSale($request);

        $this->assertDatabaseHas('fin_customer_payments', [
            'external_reference' => $externalRef,
            'amount' => db_decimal_format(150),
        ]);
    }

    /**
     * Test transaction rollback on failure
     */
    public function test_it_rolls_back_transaction_on_payment_failure()
    {
        $invoice = $this->createTestInvoice(400);
        $this->provider->setShouldSucceed(false);

        $paymentCountBefore = DB::table('fin_customer_payments')->count();

        try {
            $request = ['invoice' => $invoice];
            $this->provider->executeSale($request);
        } catch (\Exception $e) {
            // Expected
        }

        $paymentCountAfter = DB::table('fin_customer_payments')->count();
        $this->assertEquals($paymentCountBefore, $paymentCountAfter);
    }

    // Helper methods

    private function createTestInvoice($amount): Invoice
    {
        $customer = CustomerFactory::new()->create();

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::INVOICE->value,
            'payment_method_id' => PaymentMethodEnum::CREDIT_CARD->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $invoice->markApproved();
        return $invoice->fresh();
    }

    /**
     * Call protected/private method of a class.
     */
    private function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
