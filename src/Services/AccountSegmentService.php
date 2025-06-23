<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\AccountSegmentAssignment;
use Condoedge\Finance\Models\GlAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dynamic Account Segment Management Service
 * 
 * Handles the creation and management of a fully dynamic segment-based account system where:
 * - Segments define structure (position, length) dynamically
 * - Segment values are reusable building blocks 
 * - Accounts are created by combining segment values via assignments
 * - Account IDs and descriptors are computed via SQL functions
 */
class AccountSegmentService
{
    public function getLastSegmentPosition(): int
    {
        // Get the highest segment position currently defined
        return AccountSegment::max('segment_position') ?? 0;
    }

    /**
     * Get the current segment structure
     */
    public function getSegmentStructure(): Collection
    {
        return AccountSegment::orderBy('segment_position')->get();
    }

    /**
     * Create or update segment definition
     */
    public function createOrUpdateSegment(array $data): AccountSegment
    {
        return DB::transaction(function () use ($data) {
            // Validate position doesn't conflict
            $existing = AccountSegment::where('segment_position', $data['segment_position'])
                ->where('id', '!=', $data['id'] ?? 0)
                ->first();

            if ($existing) {
                throw new \InvalidArgumentException("Position {$data['segment_position']} is already taken");
            }

            return AccountSegment::updateOrCreate(
                ['id' => $data['id'] ?? null],
                [
                    'segment_description' => $data['segment_description'],
                    'segment_position' => $data['segment_position'],
                    'segment_length' => $data['segment_length'],
                    'is_active' => $data['is_active'] ?? true,
                ]
            );
        });
    }

    /**
     * Create segment value with validation
     */
    public function createSegmentValue(int $segmentDefinitionId, string $value, string $description): SegmentValue
    {
        $segmentDefinition = AccountSegment::findOrFail($segmentDefinitionId);

        // Validate value length
        if (strlen($value) > $segmentDefinition->segment_length) {
            throw new \InvalidArgumentException(
                "Value '{$value}' exceeds maximum length of {$segmentDefinition->segment_length}"
            );
        }

        return SegmentValue::create([
            'segment_definition_id' => $segmentDefinition->id,
            'segment_value' => str_pad($value, $segmentDefinition->segment_length, 0, STR_PAD_LEFT),
            'segment_description' => $description,
            'is_active' => true,
        ]);
    }

    /**
     * Create account from segment value IDs (not codes)
     * This is the dynamic approach that doesn't assume positions
     */
    public function createAccountFromSegmentValues(array $segmentValueIds, array $accountAttributes): GlAccount
    {
        return DB::transaction(function () use ($segmentValueIds, $accountAttributes) {
            // Validate we have values for all required segments
            $requiredSegments = $this->getSegmentStructure()->where('is_active', true);
            $providedSegments = SegmentValue::whereIn('id', $segmentValueIds)
                ->with('segmentDefinition')
                ->get()
                ->keyBy('segmentDefinition.segment_position');

            foreach ($requiredSegments as $segment) {
                if (!isset($providedSegments[$segment->segment_position])) {
                    throw new \InvalidArgumentException(
                        "Missing value for segment position {$segment->segment_position} ({$segment->segment_description})"
                    );
                }
            }

            // Create the account record
            $account = GlAccount::create(array_merge($accountAttributes, [
                'account_id' => 'TEMP-' . uniqid(), // Temporary ID, will be updated by trigger
                'account_segments_descriptor' => 'TEMP', // Will be updated by trigger
            ]));

            // Create segment assignments
            foreach ($segmentValueIds as $segmentValueId) {
                AccountSegmentAssignment::create([
                    'account_id' => $account->id,
                    'segment_value_id' => $segmentValueId,
                ]);
            }

            // Refresh to get computed fields from database
            return $account->refresh();
        });
    }

    /**
     * Find account by segment value combination
     */
    public function findAccountBySegmentValues(array $segmentValueIds, int $teamId): ?GlAccount
    {
        // Build a query to find accounts with exact segment combination
        $accountIds = DB::table('fin_account_segment_assignments')
            ->select('account_id')
            ->whereIn('segment_value_id', $segmentValueIds)
            ->groupBy('account_id')
            ->havingRaw('COUNT(DISTINCT segment_value_id) = ?', [count($segmentValueIds)])
            ->pluck('account_id');

        if ($accountIds->isEmpty()) {
            return null;
        }

        // Verify the account has ONLY these segments (no extra ones)
        $validAccountIds = [];
        foreach ($accountIds as $accountId) {
            $assignmentCount = AccountSegmentAssignment::where('account_id', $accountId)->count();
            if ($assignmentCount === count($segmentValueIds)) {
                $validAccountIds[] = $accountId;
            }
        }

        if (empty($validAccountIds)) {
            return null;
        }

        return GlAccount::whereIn('id', $validAccountIds)
            ->where('team_id', $teamId)
            ->first();
    }

    /**
     * Get available segment values for a segment definition
     */
    public function getSegmentValues(int $segmentDefinitionId, bool $activeOnly = true): Collection
    {
        $query = SegmentValue::where('segment_definition_id', $segmentDefinitionId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('segment_value')->get();
    }

    /**
     * Get segment values grouped by segment definition
     */
    public function getSegmentValuesGrouped(bool $activeOnly = true): Collection
    {
        $segments = $this->getSegmentStructure();

        return $segments->mapWithKeys(function ($segment) use ($activeOnly) {
            return [
                $segment->id => [
                    'definition' => $segment,
                    'values' => $this->getSegmentValues($segment->id, $activeOnly),
                ]
            ];
        });
    }

    /**
     * Update account segments (for editing last segment only)
     */
    public function updateAccountLastSegment(GlAccount $account, int $newSegmentValueId): GlAccount
    {
        return DB::transaction(function () use ($account, $newSegmentValueId) {
            // Get the new segment value and validate it
            $newSegmentValue = SegmentValue::findOrFail($newSegmentValueId);

            // Get current assignments ordered by position
            $currentAssignments = $account->segmentAssignments()
                ->join('fin_segment_values', 'fin_account_segment_assignments.segment_value_id', '=', 'fin_segment_values.id')
                ->join('fin_account_segments', 'fin_segment_values.segment_definition_id', '=', 'fin_account_segments.id')
                ->orderBy('fin_account_segments.segment_position')
                ->select('fin_account_segment_assignments.*', 'fin_account_segments.segment_position')
                ->get();

            if ($currentAssignments->isEmpty()) {
                throw new \InvalidArgumentException('Account has no segment assignments');
            }

            // Get the last segment assignment
            $lastAssignment = $currentAssignments->last();

            // Validate new value is for the same segment position
            if ($newSegmentValue->segmentDefinition->segment_position !== $lastAssignment->segment_position) {
                throw new \InvalidArgumentException('New segment value must be for the last segment position');
            }

            // Update the assignment
            $lastAssignment->update(['segment_value_id' => $newSegmentValueId]);

            // Account ID and descriptor will be updated by database trigger
            return $account->refresh();
        });
    }

    /**
     * Search accounts by partial segment pattern
     * @param array $segmentValueIds Array of segment value IDs (use null for wildcards)
     */
    public function searchAccountsByPattern(array $segmentValueIds, int $teamId): Collection
    {
        $query = GlAccount::where('team_id', $teamId);

        // Join with assignments for each non-null segment value
        foreach ($segmentValueIds as $index => $segmentValueId) {
            if ($segmentValueId !== null) {
                $alias = "asa_{$index}";
                $query->join(
                    "fin_account_segment_assignments as {$alias}",
                    "{$alias}.account_id",
                    '=',
                    'fin_gl_accounts.id'
                )->where("{$alias}.segment_value_id", $segmentValueId);
            }
        }

        return $query->distinct()->get();
    }

    /**
     * Validate segment structure completeness
     */
    public function validateSegmentStructure(): array
    {
        $issues = [];
        $segments = $this->getSegmentStructure();

        // Check for position gaps
        $positions = $segments->pluck('segment_position')->sort()->values();
        for ($i = 1; $i <= $positions->last(); $i++) {
            if (!$positions->contains($i)) {
                $issues[] = __('translate-with-values-missing-definition-of-position', ['position' => $i]);
            }
        }

        // Check each active segment has values
        foreach ($segments as $segment) {
            if ($segment->segmentValues()->whereRaw('LENGTH(fin_segment_values.segment_value) != ' . $segment->segment_length)->count() > 1) {
                $issues[] = __('translate.with-values.you-have-values-with-the-wrong-length-in', [
                    'segment' => $segment->segment_description,
                ]);
            }
        }

        return $issues;
    }

    public function getAccountFormatMask()
    {
        $segments = $this->getSegmentStructure();
        $parts = [];

        foreach ($segments as $segment) {
            $parts[] = str_repeat('X', $segment->segment_length);
        }

        return implode('-', $parts);
    }

    /**
     * Get account format example based on current structure
     */
    public function getAccountFormatExample(): string
    {
        $segments = $this->getSegmentStructure();
        $examples = [];

        foreach ($segments as $segment) {
            $value = $segment->activeSegmentValues()->first();
            $examples[] = $value ? $value->segment_value : str_repeat('0', $segment->segment_length);
        }

        return implode('-', $examples);
    }

    /**
     * Get segment statistics
     */
    public function getSegmentStatistics()
    {
        return [
            'total_segments' => AccountSegment::count(),
            'total_values' => SegmentValue::count(),
            'active_values' => SegmentValue::where('is_active', true)->count(),
            'total_accounts' => GlAccount::forTeam()->count(),
        ];
    }

    public function getSegmentsCoverageData()
    {
        return AccountSegment::orderedByPosition()->withCount([
            'segmentValues',
            'activeSegmentValues',
            'segmentValues as used_segment_values_count' => function ($query) {
                $query->whereHas('accountAssignments');
            },
        ])->get()->map(function ($segment) {
            $totalValues = $segment->segment_values_count;
            $activeValues = $segment->active_segment_values_count;
            $usedValues = $segment->used_segment_values_count;

            $usagePercentage = $totalValues > 0 ?
                round(($usedValues / $totalValues) * 100, 1) : 0;

            return [
                'segment_description' => $segment->segment_description,
                'total_values' => $totalValues,
                'active_values' => $activeValues,
                'used_values' => $usedValues,
                'usage_percentage' => $usagePercentage,
            ];
        });
    }
}
