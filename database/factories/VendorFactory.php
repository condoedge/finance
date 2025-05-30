<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Payable\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company(),
            'team_id' => 1, // Default team
            'vendor_due_amount' => 0,
        ];
    }

    public function withDue($amount = null)
    {
        return $this->state([
            'vendor_due_amount' => $amount ?? $this->faker->randomFloat(2, 100, 5000),
        ]);
    }
}
