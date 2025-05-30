<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Payable\VendorPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorPaymentFactory extends Factory
{
    protected $model = VendorPayment::class;

    public function definition()
    {
        return [
            'payment_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'amount' => $this->faker->randomFloat(2, 50, 2000),
            'amount_left' => 0, // Will be calculated
        ];
    }

    public function withAmountLeft($amount = null)
    {
        return $this->state([
            'amount_left' => $amount ?? $this->faker->randomFloat(2, 10, 500),
        ]);
    }
}
