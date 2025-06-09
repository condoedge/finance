<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\CustomableTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomableTeamFactory extends Factory
{
    protected $model = CustomableTeam::class;

    public function definition()
    {
        return [
            'team_name' => $this->faker->company,
        ];
    }
}
