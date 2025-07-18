<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Customer;
use Condoedge\Utils\Facades\TeamModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'default_payment_method_id' => null, // Will be populated later if needed
            'default_billing_address_id' => null, // Will be populated later if needed
            // 'customer_due_amount' => $this->faker->randomFloat(2, 0, 1000),
            'team_id' => TeamModel::create(['team_name' => $this->faker->company])->id,
        ];
    }

    public function configure(array $attributes = [])
    {
        return $this->afterCreating(function (Customer $customer) {
            // Create a default address for the customer
            auth()->user()?->load('currentTeamRole');

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
