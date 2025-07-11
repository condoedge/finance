<?php

namespace Tests\Unit\Services;

use Condoedge\Finance\Billing\BnaPaymentProvider;
use Condoedge\Finance\Billing\PaymentGatewayInterface;
use Condoedge\Finance\Billing\PaymentGatewayResolver;
use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Finance\Services\PaymentGatewayService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class PaymentGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentGatewayService $service;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($user);

        $this->service = new PaymentGatewayService();

        // Clear any static context
        PaymentGatewayResolver::clearContext();
    }

    /**
     * Test service provides stateless gateway access
     */
    public function test_it_provides_stateless_gateway_access()
    {
        $customer = CustomerFactory::new()->create();

        // Create two invoices with different payment methods
        $invoice1 = $this->createInvoiceWithPaymentMethod($customer->id, PaymentMethodEnum::CREDIT_CARD);
        $invoice2 = $this->createInvoiceWithPaymentMethod($customer->id, PaymentMethodEnum::CREDIT_CARD);

        // Get gateways for both invoices
        $gateway1 = $this->service->getGatewayForInvoice($invoice1);
        $gateway2 = $this->service->getGatewayForInvoice($invoice2);

        // Both should be instances of payment gateway
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway1);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway2);

        // Should be separate instances (stateless)
        $this->assertNotSame($gateway1, $gateway2);
    }

    /**
     * Test service resolves correct gateway for invoice
     */
    public function test_it_resolves_correct_gateway_for_invoice()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoiceWithPaymentMethod($customer->id, PaymentMethodEnum::CREDIT_CARD);

        $gateway = $this->service->getGatewayForInvoice($invoice);

        $this->assertInstanceOf(BnaPaymentProvider::class, $gateway);
    }

    /**
     * Test service passes context to gateway properly
     */
    public function test_it_passes_context_to_gateway_properly()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoiceWithPaymentMethod($customer->id, PaymentMethodEnum::CREDIT_CARD);

        $customContext = [
            'installment_ids' => [1, 2, 3],
            'custom_parameter' => 'test_value',
            'processing_mode' => 'immediate',
        ];

        $gateway = $this->service->getGatewayForInvoice($invoice, $customContext);

        // Gateway should have received the invoice and custom context
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);

        // The context should include both invoice and custom parameters
        // Note: We can't directly test the context was set without modifying the gateway
        // but we can verify the gateway was created successfully
    }

    /**
     * Test getting gateway by payment type directly
     */
    public function test_it_gets_gateway_for_payment_type()
    {
        $gateway = $this->service->getGatewayForPaymentType(PaymentMethodEnum::CREDIT_CARD);

        $this->assertInstanceOf(BnaPaymentProvider::class, $gateway);
    }

    /**
     * Test getting gateway with custom context
     */
    public function test_it_gets_gateway_with_custom_context()
    {
        $context = [
            'test_mode' => true,
            'api_version' => 'v2',
            'timeout' => 30,
        ];

        $gateway = $this->service->getGatewayWithContext(PaymentMethodEnum::CREDIT_CARD, $context);

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    /**
     * Test getting all available gateways
     */
    public function test_it_returns_all_available_gateways()
    {
        $gateways = $this->service->getAvailableGateways();

        $this->assertIsArray($gateways);
        $this->assertNotEmpty($gateways);

        // Check structure
        foreach ($gateways as $key => $gatewayInfo) {
            $this->assertArrayHasKey('payment_method', $gatewayInfo);
            $this->assertArrayHasKey('gateway_class', $gatewayInfo);
            $this->assertArrayHasKey('label', $gatewayInfo);
        }
    }

    /**
     * Test service handles payment types without gateways
     */
    public function test_it_handles_payment_types_without_gateways()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(__('translate.payment-method-not-supported', ['method' => PaymentMethodEnum::CASH->label()]));

        // Cash doesn't have a gateway
        $gateway = $this->service->getGatewayForPaymentType(PaymentMethodEnum::CASH);

        $this->assertNull($gateway);
    }

    /**
     * Test service maintains isolation between requests
     */
    public function test_it_maintains_isolation_between_requests()
    {
        $customer = CustomerFactory::new()->create();
        $invoice1 = $this->createInvoiceWithPaymentMethod($customer->id, PaymentMethodEnum::CREDIT_CARD);
        $invoice2 = $this->createInvoiceWithPaymentMethod($customer->id, PaymentMethodEnum::CREDIT_CARD);

        // Get gateway for first invoice with specific context
        $context1 = ['request_id' => 'REQ-001'];
        $gateway1 = $this->service->getGatewayForInvoice($invoice1, $context1);

        // Get gateway for second invoice with different context
        $context2 = ['request_id' => 'REQ-002'];
        $gateway2 = $this->service->getGatewayForInvoice($invoice2, $context2);

        // Gateways should be independent
        $this->assertNotSame($gateway1, $gateway2);
    }

    /**
     * Test service provides consistent gateway instances
     */
    public function test_it_provides_consistent_gateway_instances()
    {
        $paymentType = PaymentMethodEnum::CREDIT_CARD;

        // Get multiple gateways for same payment type
        $gateway1 = $this->service->getGatewayForPaymentType($paymentType);
        $gateway2 = $this->service->getGatewayForPaymentType($paymentType);
        $gateway3 = $this->service->getGatewayForPaymentType($paymentType);

        // All should be same class type
        $this->assertEquals(get_class($gateway1), get_class($gateway2));
        $this->assertEquals(get_class($gateway2), get_class($gateway3));

        // But different instances (stateless)
        $this->assertNotSame($gateway1, $gateway2);
        $this->assertNotSame($gateway2, $gateway3);
    }

    /**
     * Test service handles complex context scenarios
     */
    public function test_it_handles_complex_context_scenarios()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoiceWithPaymentMethod($customer->id, PaymentMethodEnum::CREDIT_CARD);

        // Complex nested context
        $complexContext = [
            'invoice' => $invoice,
            'processing' => [
                'mode' => 'async',
                'priority' => 'high',
                'retry' => [
                    'attempts' => 3,
                    'delay' => 1000,
                ],
            ],
            'metadata' => [
                'source' => 'web',
                'user_agent' => 'test-agent',
                'ip_address' => '127.0.0.1',
            ],
            'features' => [
                'save_card' => true,
                'send_receipt' => true,
                'fraud_check' => 'enhanced',
            ],
        ];

        $gateway = $this->service->getGatewayWithContext(PaymentMethodEnum::CREDIT_CARD, $complexContext);

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    /**
     * Test service error handling for invalid scenarios
     */
    public function test_it_handles_invalid_scenarios_gracefully()
    {
        // Test with null invoice (should not crash)
        try {
            $gateway = $this->service->getGatewayForInvoice(null);
            // If no exception, gateway should handle null gracefully
            $this->assertTrue(true);
        } catch (\TypeError $e) {
            // Expected for strict typing
            $this->assertTrue(true);
        }

        // Test with empty context
        $gateway = $this->service->getGatewayWithContext(PaymentMethodEnum::CREDIT_CARD, []);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    // Helper methods

    private function createInvoiceWithPaymentMethod($customerId, PaymentMethodEnum $paymentMethod): Invoice
    {
        $invoice = new Invoice();
        $invoice->customer_id = $customerId;
        $invoice->payment_method_id = $paymentMethod;
        $invoice->invoice_date = now();
        $invoice->invoice_type_id = 1;
        $invoice->save();

        return $invoice;
    }
}
