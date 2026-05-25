<?php

namespace Tests\Unit;

use Condoedge\Finance\Database\Factories\CustomerFactory;
use Condoedge\Finance\Facades\PaymentService;
use Condoedge\Finance\Models\Dto\Payments\CreateCustomerPaymentDto;
use Exception;
use Illuminate\Foundation\Testing\WithFaker;
use Kompo\Auth\Database\Factories\UserFactory;
use Tests\TestCase;

class CustomerPaymentFeesTest extends TestCase
{
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        $user = UserFactory::new()->create()->first();
        if (!$user) {
            throw new Exception('Unknown error creating user');
        }
        $this->actingAs($user);
    }

    public function test_processor_fees_is_persisted()
    {
        $customer = CustomerFactory::new()->create();

        $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $customer->id,
            'amount' => 100,
            'processor_fees' => 2.50,
            'payment_date' => now(),
        ]));

        $this->assertDatabaseHas('fin_customer_payments', [
            'id' => $payment->id,
            'processor_fees' => db_decimal_format(2.50),
        ]);
        $this->assertEqualsDecimals(2.50, $payment->processor_fees);
    }

    public function test_net_equals_amount_minus_processor_fees()
    {
        $customer = CustomerFactory::new()->create();

        $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $customer->id,
            'amount' => 100,
            'processor_fees' => 3,
            'payment_date' => now(),
        ]));

        $payment->refresh();
        $this->assertEqualsDecimals(97, $payment->net);
    }

    public function test_net_equals_amount_when_no_processor_fees()
    {
        $customer = CustomerFactory::new()->create();

        $payment = PaymentService::createPayment(new CreateCustomerPaymentDto([
            'customer_id' => $customer->id,
            'amount' => 100,
            'payment_date' => now(),
        ]));

        $payment->refresh();
        $this->assertEqualsDecimals(0, $payment->processor_fees);
        $this->assertEqualsDecimals(100, $payment->net);
    }
}
