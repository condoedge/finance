<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\CustomerPayment;
use Condoedge\Finance\Models\Invoice;
use Condoedge\Finance\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    public function definition()
    {
        return [
            'invoice_id' => Invoice::factory(),
            'apply_date' => $this->faker->date(),
            'payment_applied_amount' => $this->faker->randomFloat(2, 0, 1000),
            'invoice_applied_amount' => $this->faker->randomFloat(2, 0, 1000),
            'payment_id' => CustomerPayment::factory(),
        ];
    }
}
