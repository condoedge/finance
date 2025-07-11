<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoiceTypeEnum;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

/**
 * Base test class for payment-related tests
 * 
 * Provides common helper methods and setup for payment testing
 */
abstract class PaymentTestCase extends TestCase
{
    use RefreshDatabase, WithFaker;

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
    }

    /**
     * Create a test invoice with specified amount
     */
    protected function createTestInvoice($amount, ?PaymentMethodEnum $paymentMethod = null, $draft = false): Invoice
    {
        $paymentMethod = $paymentMethod ?? PaymentMethodEnum::CREDIT_CARD;
        $customer = CustomerFactory::new()->create();

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::INVOICE->value,
            'payment_method_id' => $paymentMethod->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            'is_draft' => false,
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

        if(!$draft) $invoice->markApproved();
        return $invoice->fresh();
    }

    /**
     * Create a credit note
     */
    protected function createCreditNote($customerId, $amount): Invoice
    {
        $creditNote = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customerId,
            'invoice_type_id' => InvoiceTypeEnum::CREDIT->value,
            'payment_method_id' => PaymentMethodEnum::CASH->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            'is_draft' => false,
            'invoiceDetails' => [
                [
                    'name' => 'Credit Item',
                    'description' => 'Credit/Refund',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $creditNote->markApproved();
        return $creditNote->fresh();
    }

    /**
     * Create a customer payment
     */
    protected function createCustomerPayment($customerId, $amount, ?string $externalReference = null): CustomerPayment
    {
        return PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $customerId,
            'amount' => $amount,
            'payment_date' => now()->format('Y-m-d'),
            'external_reference' => $externalReference,
        ]));
    }

    /**
     * Get database decimal format
     */
    protected function db_decimal_format($value): string
    {
        return number_format($value, 5, '.', '');
    }

    /**
     * Get database date format
     */
    protected function db_date_format($date): string
    {
        return $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
    }

    /**
     * Call protected/private method of a class
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Access protected/private property of a class
     */
    protected function getProtectedProperty(&$object, $propertyName)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Set protected/private property of a class
     */
    protected function setProtectedProperty(&$object, $propertyName, $value): void
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Create test payment request data
     */
    protected function createTestPaymentRequest($amount = null, array $overrides = []): array
    {
        $defaultRequest = [
            'amount' => $amount ?? $this->faker->randomFloat(2, 10, 1000),
            'card_information' => '4111111111111111',
            'complete_name' => $this->faker->name,
            'expiration_date' => '12/' . (date('y') + 2),
            'card_cvc' => '123',
            'billing_address' => [
                'street' => $this->faker->streetAddress,
                'city' => $this->faker->city,
                'state' => $this->faker->stateAbbr,
                'postal_code' => $this->faker->postcode,
                'country' => 'US',
            ],
        ];

        return array_merge($defaultRequest, $overrides);
    }

    /**
     * Assert payment was created with expected values
     */
    protected function assertPaymentCreated($expectedAmount, $expectedCustomerId, ?string $externalReference = null): CustomerPayment
    {
        $query = CustomerPayment::where('customer_id', $expectedCustomerId)
            ->where('amount', $this->db_decimal_format($expectedAmount));
            
        if ($externalReference) {
            $query->where('external_reference', $externalReference);
        }

        $payment = $query->first();
        
        $this->assertNotNull($payment, 'Payment was not created');
        $this->assertEqualsDecimals($expectedAmount, $payment->amount);
        $this->assertEquals($expectedCustomerId, $payment->customer_id);
        
        if ($externalReference) {
            $this->assertEquals($externalReference, $payment->external_reference);
        }

        return $payment;
    }

    /**
     * Assert invoice has expected due amount
     */
    protected function assertInvoiceDueAmount(Invoice $invoice, $expectedAmount): void
    {
        $invoice->refresh();
        $this->assertEqualsDecimals($expectedAmount, $invoice->invoice_due_amount);
    }

    /**
     * Assert customer has expected due amount
     */
    protected function assertCustomerDueAmount($customerId, $expectedAmount): void
    {
        $customer = \Condoedge\Finance\Models\Customer::find($customerId);
        $this->assertNotNull($customer);
        $this->assertEqualsDecimals($expectedAmount, $customer->customer_due_amount);
    }
}
