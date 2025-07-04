<?php

namespace Condoedge\Finance\Database\Factories;

use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\SegmentValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class GlAccountFactory extends Factory
{
    protected $model = GlAccount::class;

    public function definition()
    {
        return [
            'account_segments_descriptor' => $this->faker->unique()->company,
            // 'account_id' => $this->faker->unique()->regexify('[A-Z]{4}-[0-9]{3}'),
            // 'account_description' => $this->faker->sentence(3),
            'is_active' => true,
            'allow_manual_entry' => true,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (GlAccount $account) {
            $segmentValue = SegmentValue::factory()->create();

            $account->segmentValues()->attach($segmentValue->id, [
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        });
    }
}
