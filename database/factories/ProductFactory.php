<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Account;
use Condoedge\Finance\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'default_revenue_account_id' => Account::factory(),
            
        ];
    }
}
