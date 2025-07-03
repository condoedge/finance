<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Facades\SegmentDefaultHandlerEnum as FacadesSegmentDefaultHandlerEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\FiscalPeriod;
use Condoedge\Finance\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Segment Default Handler Service
 * 
 * Handles automatic generation of segment values based on configured handlers.
 * Each segment can have a default handler that generates values automatically
 * based on context like team, fiscal year, or sequences.
 */
class SegmentDefaultHandlerService
{
    /**
     * Resolve default value for a segment based on its handler
     * 
     * @param AccountSegment $segment The segment definition
     * @param array $context Context data (team_id, fiscal_year_id, etc.)
     * @return SegmentValue|null The resolved segment value or null
     */
    public function resolveDefaultValue(AccountSegment $segment, array $context = []): ?SegmentValue
    {
        if (!$segment->default_handler) {
            return null;
        }

        $handler = SegmentDefaultHandlerEnum::tryFrom($segment->default_handler);
        if (!$handler) {
            Log::warning('Invalid default handler for segment', [
                'segment_id' => $segment->id,
                'handler' => $segment->default_handler,
            ]);
            return null;
        }

        try {
            return match($handler) {
                SegmentDefaultHandlerEnum::TEAM => $this->resolveTeamValue($segment, $context),
                SegmentDefaultHandlerEnum::PARENT_TEAM => $this->resolveParentTeamValue($segment, $context),
                SegmentDefaultHandlerEnum::MANUAL => null,
            };
        } catch (\Exception $e) {
            Log::error('Error resolving default value for segment', [
                'segment_id' => $segment->id,
                'handler' => $handler->value,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve team-based segment value
     */
    protected function resolveTeamValue(AccountSegment $segment, array $context): ?SegmentValue
    {
        $teamId = $context['team_id'] ?? currentTeamId();
        if (!$teamId) {
            return null;
        }

        // For team handler, we use the team ID padded to segment length
        $code = str_pad((string)$teamId, $segment->segment_length, '0', STR_PAD_LEFT);
        
        // If code is too long, take the last N characters
        if (strlen($code) > $segment->segment_length) {
            $code = substr($code, -$segment->segment_length);
        }

        $description = __('finance.team-id-value', ['id' => $teamId]);

        return $this->findOrCreateSegmentValue($segment, $code, $description);
    }

    /**
     * Resolve parent team segment value
     */
    protected function resolveParentTeamValue(AccountSegment $segment, array $context): ?SegmentValue
    {
        $teamId = $context['team_id'] ?? currentTeamId();
        if (!$teamId) {
            return null;
        }

        $team = \Kompo\Auth\Facades\TeamModel::find($teamId);
        $code = $team->parent_team_id ?? 0;
        $parentName = $team->parentTeam ? $team->parentTeam->team_name : __('finance.no-parent-team');

        $code = $this->fitToSegmentLength($code, $segment->segment_length);

        return $this->findOrCreateSegmentValue($segment, $code, $parentName);
    }

    /**
     * Find or create segment value
     */
    protected function findOrCreateSegmentValue(
        AccountSegment $segment,
        string $code,
        string $description
    ): SegmentValue {
        // Ensure code fits segment length exactly
        $code = $this->fitToSegmentLength($code, $segment->segment_length);

        // Try to find existing value
        $segmentValue = SegmentValue::where('segment_definition_id', $segment->id)
            ->where('segment_value', $code)
            ->first();

        if ($segmentValue) {
            // Update description if changed and value is active
            if ($segmentValue->is_active && $segmentValue->segment_description !== $description) {
                $segmentValue->segment_description = $description;
                $segmentValue->save();
            }
            return $segmentValue;
        }

        // Create new segment value
        $segmentValue = new SegmentValue();
        $segmentValue->segment_definition_id = $segment->id;
        $segmentValue->segment_value = $code;
        $segmentValue->segment_description = $description;
        $segmentValue->is_active = true;
        $segmentValue->save();

        return $segmentValue;
    }

    /**
     * Get next sequence value
     */
    protected function getNextSequenceValue(string $sequenceKey, ?int $teamId, string $scope, int $startValue): int
    {
        return DB::transaction(function () use ($sequenceKey, $teamId, $scope, $startValue) {
            $sequence = DB::table('fin_segment_sequences')
                ->where('sequence_key', $sequenceKey)
                ->where('team_id', $teamId)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                // Create new sequence
                DB::table('fin_segment_sequences')->insert([
                    'sequence_key' => $sequenceKey,
                    'team_id' => $teamId,
                    'scope' => $scope,
                    'current_value' => $startValue,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return $startValue;
            }

            // Increment and return
            $nextValue = $sequence->current_value + 1;
            DB::table('fin_segment_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'current_value' => $nextValue,
                    'updated_at' => now(),
                ]);

            return $nextValue;
        });
    }

    /**
     * Fit a value to segment length
     */
    protected function fitToSegmentLength(string $value, int $length): string
    {
        if (strlen($value) > $length) {
            // Take the last N characters if too long
            return substr($value, -$length);
        } elseif (strlen($value) < $length) {
            // Pad with zeros if too short
            return str_pad($value, $length, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    /**
     * Validate handler configuration for a segment
     */
    public function validateHandlerConfig(AccountSegment $segment): array
    {
        if (!$segment->default_handler) {
            return [];
        }

        $handler = SegmentDefaultHandlerEnum::tryFrom($segment->default_handler);
        if (!$handler) {
            return ['default_handler' => __('finance.invalid-handler-type')];
        }

        return $handler->validateConfig($segment->default_handler_config);
    }

    /**
     * Get handlers suitable for a segment position
     * Some handlers might be more appropriate for certain positions
     */
    public function getAvailableHandlers(int $segmentPosition): array
    {
        // All handlers are available for all positions
        // But we can add business logic here if needed
        return collect(FacadesSegmentDefaultHandlerEnum::cases())
            ->mapWithKeys(fn($handler) => [
                $handler->value => [
                    'label' => $handler->label(),
                    'description' => $handler->description(),
                    'requires_config' => $handler->requiresConfig(),
                    'config_fields' => $handler->getConfigFields(),
                ]
            ])
            ->all();
    }
}
