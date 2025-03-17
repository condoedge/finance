<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company,
        ];
    }
}