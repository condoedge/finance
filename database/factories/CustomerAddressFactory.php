<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Customer;
use Condoedge\Finance\Models\CustomerAddress;
use Condoedge\Finance\Models\TaxGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition()
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => $this->faker->optional()->company,
            'address' => $this->faker->address,
            'city' => $this->faker->city,
            'state' => $this->faker->state,
            'country' => $this->faker->country,
            'postal_code' => $this->faker->postcode,
            'default_tax_group_id' => $this->faker->optional()->randomElement([TaxGroup::factory(), null]),
        ];
    }
}
