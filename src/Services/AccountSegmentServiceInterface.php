<?php

namespace Condoedge\Finance\Services;

use Condoedge\Finance\Models\AccountSegment;
use Condoedge\Finance\Models\SegmentValue;
use Condoedge\Finance\Models\GlAccount;
use Condoedge\Finance\Models\Dto\Gl\CreateAccountDto;
use Condoedge\Finance\Models\Dto\Gl\CreateOrUpdateSegmentDto;
use Condoedge\Finance\Models\Dto\Gl\CreateSegmentValueDto;
use Illuminate\Support\Collection;

/**
 * Account Segment Service Interface
 * 
 * Defines the contract for account segment management.
 * This allows for easy overriding and customization of the segment system.
 */
interface AccountSegmentServiceInterface
{
    /**
     * Get the current segment structure
     */
    public function getSegmentStructure(): Collection;
    
    /**
     * Get the last segment position number
     */
    public function getLastSegmentPosition(): int;
    
    /**
     * Create or update a segment definition
     */
    public function createOrUpdateSegment(CreateOrUpdateSegmentDto $dto): AccountSegment;
    
    /**
     * Create a new segment value
     */
    public function createSegmentValue(CreateSegmentValueDto $dto): SegmentValue;
    
    /**
     * Create an account from segment values
     */
    public function createAccount(CreateAccountDto $dto): GlAccount;
    
    /**
     * Search accounts by partial segment pattern
     */
    public function searchAccountsByPattern(array $segmentValueIds): Collection;
    
    /**
     * Get segment values for a specific segment definition
     */
    public function getSegmentValues(int $segmentDefinitionId, bool $activeOnly = true): Collection;
    
    /**
     * Get segment values grouped by definition
     */
    public function getSegmentValuesGrouped(bool $activeOnly = true): Collection;
    
    /**
     * Validate segment structure completeness
     */
    public function validateSegmentStructure(): array;
    
    /**
     * Get account format mask (e.g., "XX-XX-XXXX")
     */
    public function getAccountFormatMask(): string;
    
    /**
     * Get account format example based on current structure
     */
    public function getAccountFormatExample(): string;
    
    /**
     * Get segment statistics
     */
    public function getSegmentStatistics(): array;
    
    /**
     * Get segments coverage data
     */
    public function getSegmentsCoverageData(): Collection;

    public function deleteSegment(int $segmentId): bool;
    
    /**
     * Apply default handlers to fill missing segment values
     */
    public function applyDefaultHandlers(array $providedSegmentValueIds, array $context): array;
    
    /**
     * Create account with smart defaults
     */
    public function createAccountWithDefaults(array $manualSegments, array $accountData): GlAccount;
    
    /**
     * Create a complete account by only providing the last segment value ID
     */
    public function createAccountFromLastValue($lastSegmentValueId): GlAccount;
    
    /**
     * Get the last segment definition
     */
    public function getLastSegment(): ?AccountSegment;
    
    /**
     * Check if all segments except the last have default handlers
     */
    public function canCreateAccountFromLastSegmentOnly(): bool;
    
    /**
     * Get segment handler options for UI
     */
    public function getSegmentHandlerOptions(int $segmentPosition): array;

    public function createDefaultSegments(): void;
}
