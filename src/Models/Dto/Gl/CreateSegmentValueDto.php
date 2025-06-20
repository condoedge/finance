<?php

namespace Condoedge\Finance\Models\Dto\Gl;

use Condoedge\Finance\Models\Dto\BaseDto;

/**
 * DTO for creating segment values
 */
class CreateSegmentValueDto extends BaseDto
{
    public function __construct(
        public int $position,
        public string $value,
        public string $description,
        public bool $isActive = true
    ) {}
    
    public static function create(int $position, string $value, string $description, bool $isActive = true): self
    {
        return new self($position, $value, $description, $isActive);
    }
    
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'value' => $this->value,
            'description' => $this->description,
            'is_active' => $this->isActive,
        ];
    }
}
