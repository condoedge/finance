<?php

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $teamSegment = DB::table('fin_account_segments')
            ->where('default_handler', SegmentDefaultHandlerEnum::TEAM->value)
            ->first();

        if ($teamSegment) {
            // Only grow the slot — never shrink, never reorder.
            if ($teamSegment->segment_length < 7) {
                DB::table('fin_account_segments')
                    ->where('id', $teamSegment->id)
                    ->update(['segment_length' => 7, 'updated_at' => now()]);
            }
            return;
        }

        // No team segment exists — create one at the next free position.
        $nextPosition = (int) DB::table('fin_account_segments')->max('segment_position') + 1;
        DB::table('fin_account_segments')->insert([
            'segment_description' => 'Team',
            'segment_position' => $nextPosition,
            'segment_length' => 7,
            'default_handler' => SegmentDefaultHandlerEnum::TEAM->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Non-destructive migration — nothing safe to reverse.
    }
};
