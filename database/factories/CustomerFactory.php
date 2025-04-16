<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Condoedge\Utils\Facades\TeamModel;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'default_payment_type_id' => null, // Will be populated later if needed
            'default_billing_address_id' => null, // Will be populated later if needed
            'customer_due_amount' => $this->faker->randomFloat(2, 0, 1000),
            'team_id' => TeamModel::create(['team_name' => $this->faker->company])->id,
        ];
    }

    public function configure(array $attributes = [])
    {
        return $this->afterCreating(function (Customer $customer) {
            // Create a default address for the customer
            $address = $customer->addresses()->create([
                'address1' => $this->faker->streetAddress,
                'city' => $this->faker->city,
                'state' => $this->faker->state,
                'postal_code' => $this->faker->postcode,
                'country' => $this->faker->country,
            ]);

            \DB::table('fin_customers')
                ->where('id', $customer->id)
                ->update(['default_billing_address_id' => $address->id]);
        });
    }
}
