<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Payable\BillDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillDetailFactory extends Factory
{
    protected $model = BillDetail::class;

    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(1, 10),
            'unit_price' => $this->faker->randomFloat(2, 10, 500),
            'expense_account_id' => 1, // Default expense account
        ];
    }

    public function service()
    {
        return $this->state([
            'name' => 'Professional Services',
            'description' => $this->faker->sentence(),
            'quantity' => 1,
            'unit_price' => $this->faker->randomFloat(2, 100, 1000),
        ]);
    }

    public function material()
    {
        return $this->state([
            'name' => $this->faker->words(2, true) . ' Material',
            'description' => 'Construction material',
            'quantity' => $this->faker->numberBetween(5, 50),
            'unit_price' => $this->faker->randomFloat(2, 5, 100),
        ]);
    }
}
