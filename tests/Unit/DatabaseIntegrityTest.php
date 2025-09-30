<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Database\Factories\GlAccountFactory;
use Condoedge\Finance\Database\Factories\PaymentTermFactory;
use Condoedge\Finance\Facades\CustomerModel;
use Condoedge\Finance\Facades\InvoiceService;
use Condoedge\Finance\Facades\InvoiceTypeEnum;
use Condoedge\Finance\Facades\PaymentMethodEnum;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Dto\Invoices\CreateInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateApplyForInvoiceDto;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\MorphablesEnum;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Kompo\Auth\Models\User $user */
        $user = UserFactory::new()->create()->first();
        if (!$user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($user);
    }

    public function test_it_validates_calculate_payment_amount_left_function()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 1000);
        $invoice1 = $this->createInvoice($customer->id, 300);
        $invoice2 = $this->createInvoice($customer->id, 400);

        // Test initial calculation
        $amountLeft = DB::selectOne('SELECT calculate_payment_amount_left(?) as amount_left', [$payment->id]);
        $this->assertEquals(1000, $amountLeft->amount_left);

        // Apply first payment
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 200,
            'invoice_id' => $invoice1->id,
        ]));

        // Test calculation after first application
        $amountLeft = DB::selectOne('SELECT calculate_payment_amount_left(?) as amount_left', [$payment->id]);
        $this->assertEquals(800, $amountLeft->amount_left);

        // Apply second payment
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 350,
            'invoice_id' => $invoice2->id,
        ]));

        // Test calculation after second application
        $amountLeft = DB::selectOne('SELECT calculate_payment_amount_left(?) as amount_left', [$payment->id]);
        $this->assertEquals(450, $amountLeft->amount_left);
    }

    public function test_it_validates_calculate_invoice_due_function()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 500);
        $payment1 = $this->createCustomerPayment($customer->id, 200);
        $payment2 = $this->createCustomerPayment($customer->id, 150);

        // Test initial calculation
        $invoiceDue = DB::selectOne('SELECT calculate_invoice_due(?) as invoice_due', [$invoice->id]);
        $this->assertEquals(500, $invoiceDue->invoice_due);

        // Apply first payment
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment1->id,
                'customer_id' => $payment1->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 150,
            'invoice_id' => $invoice->id,
        ]));

        // Test calculation after first application
        $invoiceDue = DB::selectOne('SELECT calculate_invoice_due(?) as invoice_due', [$invoice->id]);
        $this->assertEquals(350, $invoiceDue->invoice_due);

        // Apply second payment
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment2->id,
                'customer_id' => $payment2->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 100,
            'invoice_id' => $invoice->id,
        ]));

        // Test calculation after second application
        $invoiceDue = DB::selectOne('SELECT calculate_invoice_due(?) as invoice_due', [$invoice->id]);
        $this->assertEquals(250, $invoiceDue->invoice_due);
    }

    public function test_it_validates_invoice_payment_integrity_trigger_on_insert()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 100);
        $payment = $this->createCustomerPayment($customer->id, 50);

        // Valid insert should work
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 50,
            'invoice_id' => $invoice->id,
        ]));

        // Verify the trigger updated the payment amount_left
        $payment->refresh();
        $this->assertEquals(0, $payment->amount_left->toFloat());

        // Verify the trigger updated the invoice due amount
        $invoice->refresh();
        $this->assertEquals(50, $invoice->invoice_due_amount->toFloat());
    }

    public function test_it_validates_invoice_payment_integrity_trigger_prevents_overapplication()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 100);
        $payment = $this->createCustomerPayment($customer->id, 80);

        // Apply partial payment first
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 60,
            'invoice_id' => $invoice->id,
        ]));

        // Now try to directly insert an invalid application that would exceed payment amount
        try {
            DB::table('fin_invoice_applies')->insert([
                'invoice_id' => $invoice->id,
                'applicable_id' => $payment->id,
                'applicable_type' => MorphablesEnum::PAYMENT->value,
                'payment_applied_amount' => 30, // 60 + 30 = 90 > 80 payment amount
                'apply_date' => now()->format('Y-m-d'),
                'created_at' => now()->format('Y-m-d'),
                'updated_at' => now()->format('Y-m-d'),
            ]);

            // If we get here, the trigger didn't work properly
            $this->fail('Expected database constraint violation due to trigger');
        } catch (\Exception $e) {
            // Expected - the trigger should prevent this
            $this->assertStringContainsString('Payment amount exceeds the payment left', $e->getMessage());
        }
    }

    public function test_it_validates_invoice_payment_integrity_trigger_prevents_invoice_overapplication()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 100);
        $payment = $this->createCustomerPayment($customer->id, 200);

        // Apply partial payment first
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 80,
            'invoice_id' => $invoice->id,
        ]));

        // Now try to directly insert an invalid application that would exceed invoice due amount
        try {
            DB::table('fin_invoice_applies')->insert([
                'invoice_id' => $invoice->id,
                'applicable_id' => $payment->id,
                'applicable_type' => MorphablesEnum::PAYMENT->value,
                'payment_applied_amount' => 30, // 80 + 30 = 110 > 100 invoice amount
                'apply_date' => now()->format('Y-m-d'),
                'created_at' => now()->format('Y-m-d'),
                'updated_at' => now()->format('Y-m-d'),
            ]);

            // If we get here, the trigger didn't work properly
            $this->fail('Expected database constraint violation due to trigger');
        } catch (\Exception $e) {
            // Expected - the trigger should prevent this
            $this->assertStringContainsString('Payment amount exceeds the invoice left', $e->getMessage());
        }
    }

    public function test_it_validates_trigger_updates_on_delete()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 100);
        $payment = $this->createCustomerPayment($customer->id, 200);

        // Create application
        $application = PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 80,
            'invoice_id' => $invoice->id,
        ]));

        // Verify state after application
        $payment->refresh();
        $invoice->refresh();
        $this->assertEqualsDecimals(120, $payment->amount_left); // 200 - 80
        $this->assertEqualsDecimals($invoice->invoice_total_amount->subtract(80), $invoice->invoice_due_amount); // invoice-amount - 80

        // Delete application
        $application->delete();

        // Verify trigger restored original amounts
        $payment->refresh();
        $invoice->refresh();
        $this->assertEqualsDecimals(200, $payment->amount_left); // Back to original
        $this->assertEqualsDecimals(100, $invoice->invoice_due_amount); // Back to original
    }

    public function test_it_validates_trigger_updates_on_update()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 100);
        $payment = $this->createCustomerPayment($customer->id, 200);

        // Create application
        $application = PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 80,
            'invoice_id' => $invoice->id,
        ]));

        // Verify initial state
        $payment->refresh();
        $invoice->refresh();
        $this->assertEqualsDecimals(120, $payment->amount_left);
        $this->assertEqualsDecimals(20, $invoice->invoice_due_amount);

        // Update application amount (via direct DB update to test trigger)
        DB::table('fin_invoice_applies')
            ->where('id', $application->id)
            ->update(['payment_applied_amount' => 60]);

        // Verify trigger recalculated amounts
        $payment->refresh();
        $invoice->refresh();
        $this->assertEqualsDecimals(140, $payment->amount_left); // 200 - 60
        $this->assertEqualsDecimals(40, $invoice->invoice_due_amount); // 100 - 60
    }

    public function test_it_validates_customer_due_amount_calculation_with_multiple_invoices()
    {
        $customer = CustomerFactory::new()->create();
        $invoice1 = $this->createInvoice($customer->id, 300);
        $invoice2 = $this->createInvoice($customer->id, 200);
        $payment = $this->createCustomerPayment($customer->id, 400);

        // Initial customer due amount should be 100 (300 + 200 - 400)
        $customer->refresh();
        $this->assertEqualsDecimals(100, $customer->customer_due_amount);

        // Apply payment to first invoice
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 250,
            'invoice_id' => $invoice1->id,
        ]));

        // Customer due amount should be the same
        $customer->refresh();
        $this->assertEqualsDecimals(100, $customer->customer_due_amount);

        // Apply payment to second invoice
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $payment->id,
                'customer_id' => $payment->customer_id,
            ],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 150,
            'invoice_id' => $invoice2->id,
        ]));

        // Customer due amount should be the same
        $customer->refresh();
        $this->assertEqualsDecimals(100, $customer->customer_due_amount);
    }

    public function test_it_validates_credit_note_application_with_database_functions()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 500);
        $creditNote = $this->createCreditNote($customer->id, 200);

        // Test initial due amounts
        $invoiceDue = DB::selectOne('SELECT calculate_invoice_due(?) as invoice_due', [$invoice->id]);
        $creditDue = DB::selectOne('SELECT calculate_invoice_due(?) as invoice_due', [$creditNote->id]);

        $this->assertEquals(500, $invoiceDue->invoice_due);
        $this->assertEquals(-200, $creditDue->invoice_due); // Credit notes have negative due amounts

        // Apply credit note to invoice
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)[
                'id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
            ],
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'amount_applied' => 200,
            'invoice_id' => $invoice->id,
        ]));

        // Test due amounts after application
        $invoiceDue = DB::selectOne('SELECT calculate_invoice_due(?) as invoice_due', [$invoice->id]);
        $creditDue = DB::selectOne('SELECT calculate_invoice_due(?) as invoice_due', [$creditNote->id]);

        $this->assertEquals(300, $invoiceDue->invoice_due); // 500 - 200
        $this->assertEquals(0, $creditDue->invoice_due); // -200 + 200 = 0
    }

    public function test_it_validates_complex_scenario_with_multiple_payments_and_invoices()
    {
        $customer = CustomerFactory::new()->create();

        // Create multiple invoices
        $invoice1 = $this->createInvoice($customer->id, 300);
        $invoice2 = $this->createInvoice($customer->id, 400);
        $invoice3 = $this->createInvoice($customer->id, 200);

        // Create multiple payments
        $payment1 = $this->createCustomerPayment($customer->id, 250);
        $payment2 = $this->createCustomerPayment($customer->id, 350);
        $payment3 = $this->createCustomerPayment($customer->id, 150);

        // Create a credit note
        $creditNote = $this->createCreditNote($customer->id, 100);

        // Complex application scenario
        // Payment 1 -> Invoice 1 (partial)
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)['id' => $payment1->id, 'customer_id' => $payment1->customer_id],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 200,
            'invoice_id' => $invoice1->id,
        ]));

        // Payment 1 -> Invoice 2 (remaining)
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)['id' => $payment1->id, 'customer_id' => $payment1->customer_id],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 50,
            'invoice_id' => $invoice2->id,
        ]));

        // Payment 2 -> Invoice 2 (partial)
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)['id' => $payment2->id, 'customer_id' => $payment2->customer_id],
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'amount_applied' => 300,
            'invoice_id' => $invoice2->id,
        ]));

        // Credit Note -> Invoice 1 (remaining)
        PaymentService::applyPaymentToInvoice(new CreateApplyForInvoiceDto([
            'apply_date' => now()->format('Y-m-d'),
            'applicable' => (object)['id' => $creditNote->id, 'customer_id' => $creditNote->customer_id],
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'amount_applied' => 100,
            'invoice_id' => $invoice1->id,
        ]));

        // Validate final state
        $payment1->refresh();
        $payment2->refresh();
        $payment3->refresh();
        $invoice1->refresh();
        $invoice2->refresh();
        $invoice3->refresh();
        $creditNote->refresh();
        $customer->refresh();

        // Payment amounts left
        $this->assertEquals(0, $payment1->amount_left->toFloat()); // 250 - 200 - 50
        $this->assertEquals(50, $payment2->amount_left->toFloat()); // 350 - 300
        $this->assertEquals(150, $payment3->amount_left->toFloat()); // Unused

        // Invoice due amounts
        $this->assertEquals(0, $invoice1->invoice_due_amount->toFloat()); // 300 - 200 - 100
        $this->assertEquals(50, $invoice2->invoice_due_amount->toFloat()); // 400 - 50 - 300
        $this->assertEquals(200, $invoice3->invoice_due_amount->toFloat()); // Unpaid

        // Credit note due amount
        $this->assertEquals(0, $creditNote->abs_invoice_due_amount->toFloat()); // Fully applied
        $this->assertEquals(50, $customer->customer_due_amount->toFloat()); // 0 + 50 + 200
    }

    public function test_it_validates_trigger_prevents_payment_overapplication_on_direct_insert()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 100);
        $invoice = $this->createInvoice($customer->id, 200);

        // First insert a valid application
        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 80,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Now try to directly insert another application that would exceed payment amount
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment amount exceeds the payment left');

        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 30, // 80 + 30 = 110 > 100 payment amount
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_validates_trigger_prevents_invoice_overapplication_on_direct_insert()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 100);
        $payment = $this->createCustomerPayment($customer->id, 200);

        // First insert a valid application
        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 80,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Now try to directly insert another application that would exceed invoice due amount
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment amount exceeds the invoice left');

        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 30, // 80 + 30 = 110 > 100 invoice amount
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_validates_trigger_prevents_zero_amount_application_on_direct_insert()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 100);
        $invoice = $this->createInvoice($customer->id, 200);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');

        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 0,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // This behaviour is just expected in applies. In unit prices we changed that
    public function test_it_validates_trigger_converts_negative_amount_into_positive()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 100);
        $invoice = $this->createInvoice($customer->id, 200);

        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => -50,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $latestApply = DB::table('fin_invoice_applies')
            ->where('invoice_id', $invoice->id)
            ->where('applicable_id', $payment->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $this->assertEqualsDecimals(50, $latestApply->payment_applied_amount);

        $payment->refresh();
        $this->assertEqualsDecimals(50, $payment->amount_left);
    }

    public function test_it_validates_trigger_prevents_draft_invoice_application_on_direct_insert()
    {
        $customer = CustomerFactory::new()->create();
        $payment = $this->createCustomerPayment($customer->id, 500);

        // Create draft invoice (not approved) directly in database
        $draftInvoiceId = $this->createInvoice($customer->id, 300, approved: false)->id;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot apply payment to a draft invoice');

        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $draftInvoiceId,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 200,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_validates_trigger_correctly_updates_amounts_on_direct_insert()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 500);
        $payment = $this->createCustomerPayment($customer->id, 300);

        // Verify initial state
        $this->assertEquals(300, $payment->fresh()->amount_left->toFloat());
        $this->assertEquals(500, $invoice->fresh()->invoice_due_amount->toFloat());

        // Insert application directly
        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 250,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify trigger updated the amounts correctly
        $this->assertEquals(50, $payment->fresh()->amount_left->toFloat()); // 300 - 250
        $this->assertEquals(250, $invoice->fresh()->invoice_due_amount->toFloat()); // 500 - 250
    }

    public function test_it_validates_trigger_correctly_handles_credit_note_application_on_direct_insert()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 500);
        $creditNote = $this->createCreditNote($customer->id, 200);

        // Verify initial state
        $this->assertEquals(500, $invoice->fresh()->invoice_due_amount->toFloat());
        $this->assertEquals(200, $creditNote->fresh()->abs_invoice_due_amount->toFloat());

        // Insert credit application directly
        DB::table('fin_invoice_applies')->insert([
            'invoice_id' => $invoice->id,
            'applicable_id' => $creditNote->id,
            'applicable_type' => MorphablesEnum::CREDIT->value,
            'payment_applied_amount' => 200,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify trigger updated the amounts correctly
        $this->assertEquals(300, $invoice->fresh()->invoice_due_amount->toFloat()); // 500 - 200
        $this->assertEquals(0, $creditNote->fresh()->abs_invoice_due_amount->toFloat()); // 200 - 200
    }

    public function test_it_validates_trigger_correctly_handles_update_on_direct_update()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 500);
        $payment = $this->createCustomerPayment($customer->id, 300);

        // Insert initial application
        $applicationId = DB::table('fin_invoice_applies')->insertGetId([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 200,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify state after initial application
        $this->assertEquals(100, $payment->fresh()->amount_left->toFloat()); // 300 - 200
        $this->assertEquals(300, $invoice->fresh()->invoice_due_amount->toFloat()); // 500 - 200

        // Update application amount directly
        DB::table('fin_invoice_applies')
            ->where('id', $applicationId)
            ->update([
                'payment_applied_amount' => 150, // Reduce from 200 to 150
                'updated_at' => now(),
            ]);

        // Verify trigger recalculated amounts correctly
        $this->assertEquals(150, $payment->fresh()->amount_left->toFloat()); // 300 - 150
        $this->assertEquals(350, $invoice->fresh()->invoice_due_amount->toFloat()); // 500 - 150
    }

    public function test_it_validates_trigger_correctly_handles_delete_on_direct_delete()
    {
        $customer = CustomerFactory::new()->create();
        $invoice = $this->createInvoice($customer->id, 500);
        $payment = $this->createCustomerPayment($customer->id, 300);

        // Insert application
        $applicationId = DB::table('fin_invoice_applies')->insertGetId([
            'invoice_id' => $invoice->id,
            'applicable_id' => $payment->id,
            'applicable_type' => MorphablesEnum::PAYMENT->value,
            'payment_applied_amount' => 250,
            'apply_date' => now()->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify state after application
        $this->assertEquals(50, $payment->fresh()->amount_left->toFloat()); // 300 - 250
        $this->assertEquals(250, $invoice->fresh()->invoice_due_amount->toFloat()); // 500 - 250

        // Delete application directly
        DB::table('fin_invoice_applies')->where('id', $applicationId)->delete();

        // Verify trigger restored original amounts
        $this->assertEquals(300, $payment->fresh()->amount_left->toFloat()); // Back to original
        $this->assertEquals(500, $invoice->fresh()->invoice_due_amount->toFloat()); // Back to original
    }

    // Helper methods

    private function createInvoice($customerId, $amount = null, $approved = true): Invoice
    {
        $customer = CustomerModel::find($customerId);
        $unitPrice = $amount ?? $this->faker->randomFloat(2, 100, 1000);

        $invoice = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('INVOICE')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            // 'invoice_due_date' => now()->addDays(30),
            'is_draft' => !$approved,
            'invoiceDetails' => [
                [
                    'name' => 'Test Item',
                    'description' => 'Test Description',
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        if ($approved) {
            $invoice->markApproved();
        }

        return $invoice->fresh();
    }

    private function createCreditNote($customerId, $amount): Invoice
    {
        $customer = CustomerModel::find($customerId);

        $creditNote = InvoiceService::createInvoice(new CreateInvoiceDto([
            'customer_id' => $customer->id,
            'invoice_type_id' => InvoiceTypeEnum::getEnumCase('CREDIT')->value,
            'payment_method_id' => PaymentMethodEnum::getEnumCase('CASH')->value,
            'payment_term_id' => PaymentTermFactory::new()->create()->id,
            'invoice_date' => now()->format('Y-m-d'),
            // 'invoice_due_date' => now()->addDays(30),
            'is_draft' => false,
            'invoiceDetails' => [
                [
                    'name' => 'Credit Item',
                    'description' => 'Credit Description',
                    'quantity' => 1,
                    'unit_price' => - abs($amount), // Now we obligate the negative or it'll get an error
                    'revenue_account_id' => GlAccountFactory::new()->create()->id,
                    'taxesIds' => [],
                ],
            ],
        ]));

        $creditNote->markApproved();
        return $creditNote->fresh();
    }

    private function createCustomerPayment($customerId, $amount): CustomerPayment
    {
        return PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $customerId,
            'amount' => $amount,
            'payment_date' => now()->format('Y-m-d'),
        ]));
    }
}
