<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Kompo\Auth\Database\Factories\TeamFactory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'default_payment_type_id' => $this->faker->randomDigit,
            'default_billing_address_id' => null, // Will be populated later if needed
            'customer_due_amount' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }
}
