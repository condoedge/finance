<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Exceptions\SegmentValueOverflowException;
use Condoedge\Finance\Facades\SegmentDefaultHandlerEnum as FacadesSegmentDefaultHandlerEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
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
     *
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
        } catch (SegmentValueOverflowException $e) {
            // Overflow must fail loud: swallowing it here would silently drop
            // the segment and produce a malformed GL account.
            throw $e;
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

        $description = __('finance.team-id-value', ['id' => $teamId]);

        // Let findOrCreateSegmentValue (via fitToSegmentLength) handle
        // zero-padding the team id to the segment slot, or throw on overflow.
        return $this->findOrCreateSegmentValue($segment, (string) $teamId, $description, $teamId);
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
        $code = (string) ($team->parent_team_id ?? 0);
        $parentName = $team->parentTeam ? $team->parentTeam->team_name : __('finance.no-parent-team');

        // findOrCreateSegmentValue (via fitToSegmentLength) is the single
        // fitting chokepoint; pass the raw value straight through.
        return $this->findOrCreateSegmentValue($segment, $code, $parentName, $team->id);
    }

    /**
     * Find or create segment value
     */
    protected function findOrCreateSegmentValue(
        AccountSegment $segment,
        string $code,
        string $description,
        ?int $teamId = null
    ): SegmentValue {
        // Ensure code fits segment length exactly (pad-or-throw) before the
        // lookup, so the lookup key matches what we would persist on create.
        $code = $this->fitToSegmentLength($code, $segment, $teamId);

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
     *
     * Pads short values with leading zeros. Throws (and logs) when the value
     * is longer than the segment slot instead of silently truncating it,
     * which would corrupt the resulting GL account.
     */
    protected function fitToSegmentLength(string $value, AccountSegment $segment, ?int $teamId = null): string
    {
        $length = $segment->segment_length;

        if (strlen($value) > $length) {
            $exception = new SegmentValueOverflowException(
                segmentId: $segment->id,
                segmentDescription: $segment->segment_description,
                segmentLength: $length,
                value: $value,
                teamId: $teamId,
            );
            Log::error('Segment value overflow', $exception->loggingContext());
            throw $exception;
        }

        if (strlen($value) < $length) {
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
            ->mapWithKeys(fn ($handler) => [
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
