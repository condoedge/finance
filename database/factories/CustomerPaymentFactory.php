<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\CustomerPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Condoedge\Utils\Facades\TeamModel;

class CustomerPaymentFactory extends Factory
{
    protected $model = CustomerPayment::class;

    public function definition()
    {
        $amount = $this->faker->randomFloat(2, 0, 1000);

        return [
            'customer_id' => Customer::factory(),
            'payment_date' => $this->faker->date(),
            'amount' => $amount,
            'amount_left' => $amount, // Initially, amount_left equals amount
        ];
    }
}