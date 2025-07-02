<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Enums\SegmentDefaultHandlerEnum;
use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\AccountSegmentAssignment;
use Condoedge\Finance\Models\AccountSegmentValue;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dynamic Account Segment Management Service
 * 
 * Handles the creation and management of a fully dynamic segment-based account system where:
 * - Segments define structure (position, length) dynamically
 * - Segment values are reusable building blocks 
 * - Accounts are created by combining segment values via assignments
 * - Account IDs and descriptors are computed via SQL functions
 * - Segments can have default handlers for automatic value generation
 */
class AccountSegmentService implements AccountSegmentServiceInterface
{
    protected AccountSegmentValidator $validator;
    protected SegmentDefaultHandlerService $handlerService;
    
    public function __construct(
        AccountSegmentValidator $validator,
        SegmentDefaultHandlerService $handlerService
    ) {
        $this->validator = $validator;
        $this->handlerService = $handlerService;
    }
    
    /**
     * Execute a callback within a database transaction with proper error handling
     * 
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    protected function executeInTransaction(callable $callback)
    {
        return DB::transaction(function () use ($callback) {
            try {
                return $callback();
            } catch (\Exception $e) {
                // Log the error with context
                Log::error('Transaction failed in AccountSegmentService', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => auth()->id(),
                    'team_id' => currentTeamId(),
                ]);
                throw $e;
            }
        });
    }
    
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
    public function createOrUpdateSegment(CreateOrUpdateSegmentDto $dto): AccountSegment
    {
        return $this->executeInTransaction(function () use ($dto) {
            // Validate last segment must be 'account' related
            if ($dto->id) {
                $segment = AccountSegment::findOrFail($dto->id);    
                $segment->segment_description = $dto->segment_description;
                $segment->segment_position = $dto->segment_position;
                $segment->segment_length = $dto->segment_length;
                $segment->default_handler = $dto->default_handler;
                $segment->default_handler_config = $dto->default_handler_config;
                $segment->save();
            } else {
                $segment = new AccountSegment();
                $segment->segment_description = $dto->segment_description;
                $segment->segment_position = $dto->segment_position;
                $segment->segment_length = $dto->segment_length;
                $segment->default_handler = $dto->default_handler;
                $segment->default_handler_config = $dto->default_handler_config;
                $segment->save();
            }

            AccountSegment::reorderPositions();

            return $segment;
        });
    }

    /**
     * Create segment value with validation
     */
    public function createSegmentValue(CreateSegmentValueDto $dto): SegmentValue
    {
        return $this->executeInTransaction(function () use ($dto) {
            $segmentDefinition = AccountSegment::findOrFail($dto->segment_definition_id);

            $segmentValue = new SegmentValue();
            $segmentValue->segment_definition_id = $segmentDefinition->id;
            $segmentValue->segment_value = str_pad($dto->segment_value, $segmentDefinition->segment_length, '0', STR_PAD_LEFT);
            $segmentValue->segment_description = $dto->segment_description;
            $segmentValue->is_active = $dto->is_active;
            $segmentValue->account_type = $dto->account_type ?? null;
            $segmentValue->save();
            
            Log::info('Created new segment value', [
                'segment_value_id' => $segmentValue->id,
                'segment_definition_id' => $dto->segment_definition_id,
                'value' => $dto->segment_value,
            ]);

            return $segmentValue;
        });
    }

    /**
     * Create account from segment value IDs (not codes)
     * This is the dynamic approach that doesn't assume positions
     */
    public function createAccount(CreateAccountDto $dto, $context = []): GlAccount
    {
        return $this->executeInTransaction(function () use ($dto, $context) {
            // Apply default handlers if enabled
            if ($dto->apply_defaults) {
                $dto->segment_value_ids = array_merge($this->applyDefaultHandlers(
                    $dto->segment_value_ids,
                    [
                        'team_id' => currentTeamId(),
                        ...$context,
                    ]
                ));
            }

            // Create the account record using property assignment
            $account = new GlAccount();
            $account->account_segments_descriptor = 'TEMP'; // Will be updated by trigger
            $account->is_active = $dto->is_active;
            $account->allow_manual_entry = $dto->allow_manual_entry;
            $account->save();

            // Create segment assignments
            AccountSegmentAssignment::createForAccount($account->id, $dto->segment_value_ids);

            // Refresh to get computed fields from database
            $account = $account->refresh();

            return $account;
        });
    }

    public function createAccountFromLastValue($lastSegmentValueId): GlAccount
    {
        return $this->createAccount(new CreateAccountDto([
            'segment_value_ids' => [$lastSegmentValueId],
            'is_active' => true,
            'allow_manual_entry' => true,
            'apply_defaults' => true,
        ]));
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

    public function deleteSegment(int $segmentId): bool
    {
        return $this->executeInTransaction(function () use ($segmentId) {
            $segment = AccountSegment::findOrFail($segmentId);

            // Check if segment can be deleted (no active values)
            if ($segment->segmentValues()->where('is_active', true)->exists()) {
                throw new \Exception(__('validation-cannot-delete-active-segment'));
            }

            // Delete the segment
            $segment->forceDelete();

            // Reorder positions after deletion
            AccountSegment::reorderPositions();

            return true;
        });
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
                $issues[] = __('with-values-you-have-values-with-the-wrong-length-in', [
                    'segment' => $segment->segment_description,
                ]);
            }
        }

        return $issues;
    }

    public function getAccountFormatMask(): string
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
    public function getSegmentStatistics(): array
    {
        return [
            'total_segments' => AccountSegment::count(),
            'total_values' => SegmentValue::count(),
            'active_values' => SegmentValue::where('is_active', true)->count(),
            'total_accounts' => GlAccount::count(),
        ];
    }

    public function getSegmentsCoverageData(): Collection
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
    
    /**
     * Apply default handlers to fill missing segment values
     * 
     * @param array $providedSegmentValueIds Array of segment value IDs (can have gaps)
     * @param array $context Context for handlers (team_id, fiscal_year_id, etc.)
     * @return array Complete array of segment value IDs
     */
    public function applyDefaultHandlers(array $providedSegmentValueIds, array $context): array
    {
        $segments = $this->getSegmentStructure();
        $finalSegmentValueIds = [];

        // Get provided segments indexed by their definition position
        $providedByPosition = [];
        foreach ($providedSegmentValueIds as $segmentValueId) {
            if (!$segmentValueId) {
                continue;
            }
            $segmentValue = SegmentValue::find($segmentValueId);
            if ($segmentValue) {
                $position = $segmentValue->segmentDefinition->segment_position;
                $providedByPosition[$position] = $segmentValueId;
            }
        }
        
        // Process each segment position
        foreach ($segments as $segment) {
            $position = $segment->segment_position;
            if (isset($providedByPosition[$position])) {
                // Use provided value
                $finalSegmentValueIds[] = $providedByPosition[$position];
            } elseif ($segment->hasDefaultHandler()) {
                // Try to resolve via handler
                $defaultValue = $this->handlerService->resolveDefaultValue($segment, $context);
                if ($defaultValue) {
                    $finalSegmentValueIds[] = $defaultValue->id;
                    Log::info('Applied default handler for segment', [
                        'segment_id' => $segment->id,
                        'handler' => $segment->default_handler,
                        'resolved_value' => $defaultValue->segment_value,
                    ]);
                }
            } else {
                // No value provided and no handler
                throw new \InvalidArgumentException(
                    __("finance.no-value-provided-for-segment", [
                        'position' => $position,
                        'description' => $segment->segment_description
                    ])
                );
            }
        }
        
        return $finalSegmentValueIds;
    }
    
    /**
     * Create account with smart defaults
     * Convenience method that automatically applies defaults
     * 
     * @param array $manualSegments Array of segment value IDs for manually specified segments
     * @param array $accountData Account attributes
     * @return GlAccount
     */
    public function createAccountWithDefaults(array $manualSegments, array $accountData): GlAccount
    {
        $dto = new CreateAccountDto(array_merge($accountData, [
            'segment_value_ids' => $manualSegments,
            'apply_defaults' => true,
        ]));
        
        return $this->createAccount($dto);
    }
    
    /**
     * Get segment handler options for UI
     */
    public function getSegmentHandlerOptions(int $segmentPosition): array
    {
        return $this->handlerService->getAvailableHandlers($segmentPosition);
    }
    
    /**
     * Get the last segment definition
     * 
     * @return AccountSegment|null
     */
    public function getLastSegment(): ?AccountSegment
    {
        return AccountSegment::orderBy('segment_position', 'desc')->first();
    }
    
    /**
     * Check if all segments except the last have default handlers
     * 
     * @return bool
     */
    public function canCreateAccountFromLastSegmentOnly(): bool
    {
        $segments = $this->getSegmentStructure();
        $lastSegment = $this->getLastSegment();
        
        if (!$lastSegment) {
            return false;
        }
        
        // Check all segments except the last have handlers
        foreach ($segments as $segment) {
            if ($segment->id !== $lastSegment->id && !$segment->hasDefaultHandler()) {
                return false;
            }
        }
        
        return true;
    }

    public function createDefaultSegments(): void
    {
        if ($this->getSegmentStructure()->isEmpty()) {
            // Create default segments if none exist
            $this->createOrUpdateSegment(new CreateOrUpdateSegmentDto([
                'segment_description' => 'Account',
                'segment_position' => 1,
                'segment_length' => 4,
                'default_handler' => SegmentDefaultHandlerEnum::MANUAL,
            ]));
        }
    }
}
