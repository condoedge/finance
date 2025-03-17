<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\TaxGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxGroupFactory extends Factory
{
    protected $model = TaxGroup::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
        ];
    }
}
