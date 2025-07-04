<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\AccountTypeEnum;
use Condoedge\Finance\Models\SegmentValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class SegmentValueFactory extends Factory
{
    protected $model = SegmentValue::class;

    public function definition()
    {
        return [
            'segment_value' => $this->faker->numberBetween(1000, 10000),
            'account_type' => AccountTypeEnum::ASSET->value,
            'segment_definition_id' => AccountSegment::latest()->first()->id,
            'segment_description' => $this->faker->sentence(3),
        ];
    }
}
