<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'rate' => $this->faker->randomFloat(2, 0, 0.2),
            'account_id' => GlAccount::factory(),
            'valide_from' => $this->faker->date(),
            'valide_to' => $this->faker->date(),
        ];
    }
}
