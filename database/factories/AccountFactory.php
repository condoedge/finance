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
            'name' => $this->faker->unique()->company,
            // 'account_id' => $this->faker->unique()->regexify('[A-Z]{4}-[0-9]{3}'),
            // 'account_description' => $this->faker->sentence(3),
            // 'is_active' => true,
            // 'allow_manual_entry' => true,
        ];
    }
}