<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Models\Dto\BaseDto;

/**
 * DTO for creating accounts from segment combinations
 */
class CreateAccountFromSegmentsDto extends BaseDto
{
    public function __construct(
        public array $segmentCodes,
        public string $accountType,
        public int $teamId,
        public ?string $accountDescription = null,
        public bool $isActive = true,
        public bool $allowManualEntry = true
    ) {}
    
    public static function create(
        array $segmentCodes,
        string $accountType,
        int $teamId,
        ?string $accountDescription = null,
        bool $isActive = true,
        bool $allowManualEntry = true
    ): self {
        return new self(
            $segmentCodes,
            $accountType,
            $teamId,
            $accountDescription,
            $isActive,
            $allowManualEntry
        );
    }
    
    /**
     * Create from account ID string
     * Example: CreateAccountFromSegmentsDto::fromAccountId('10-03-4000', 'ASSET', 1)
     */
    public static function fromAccountId(
        string $accountId,
        string $accountType,
        int $teamId,
        ?string $accountDescription = null,
        bool $isActive = true,
        bool $allowManualEntry = true
    ): self {
        $segments = explode('-', $accountId);
        $segmentCodes = [];
        
        foreach ($segments as $index => $value) {
            $segmentCodes[$index + 1] = $value; // Convert to 1-based indexing
        }
        
        return new self(
            $segmentCodes,
            $accountType,
            $teamId,
            $accountDescription,
            $isActive,
            $allowManualEntry
        );
    }
    
    public function getAccountId(): string
    {
        return implode('-', $this->segmentCodes);
    }
    
    public function toArray(): array
    {
        return [
            'segment_codes' => $this->segmentCodes,
            'account_type' => $this->accountType,
            'team_id' => $this->teamId,
            'account_description' => $this->accountDescription,
            'is_active' => $this->isActive,
            'allow_manual_entry' => $this->allowManualEntry,
        ];
    }
    
    public function toAccountAttributes(): array
    {
        return [
            'account_type' => $this->accountType,
            'team_id' => $this->teamId,
            'account_description' => $this->accountDescription,
            'is_active' => $this->isActive,
            'allow_manual_entry' => $this->allowManualEntry,
        ];
    }
}
